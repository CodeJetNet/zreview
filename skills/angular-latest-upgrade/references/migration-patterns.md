# Angular Upgrade Migration Patterns Reference

Detailed code examples and migration patterns for each phase of an Angular major version upgrade. Referenced from the main SKILL.md workflow guide.

## Table of Contents

- [Angular Material MDC Migration (v14 to v15)](#angular-material-mdc-migration-v14-to-v15)
- [Builder Migration: browser to application (v17 to v18)](#builder-migration-browser-to-application-v17-to-v18)
- [HttpClientModule Deprecation (v18+)](#httpclientmodule-deprecation-v18)
- [Guards and Resolvers: Class to Functional (v15+)](#guards-and-resolvers-class-to-functional-v15)
- [karma.conf.js Coverage Reporter Replacement](#karmaconfjs-coverage-reporter-replacement)
- [polyfills.ts Removal Steps](#polyfillsts-removal-steps)
- [Legacy test.ts Removal](#legacy-testts-removal)
- [TypeScript Config Detailed Settings](#typescript-config-detailed-settings)
- [SCSS/Sass Processor Migration](#scsssass-processor-migration)
- [Angular Budget Updates](#angular-budget-updates)
- [Docker Runtime Env Injection Patterns](#docker-runtime-env-injection-patterns)
- [Angular Persistent Build Cache](#angular-persistent-build-cache)
- [Post-Upgrade Modernization (Future Work)](#post-upgrade-modernization-future-work)

---

## Angular Material MDC Migration (v14 to v15)

If crossing the v14/v15 boundary, this is the **largest Material breaking change ever**. All Material components were rewritten to use MDC (Material Design Components) Web:

- Component selectors changed (e.g., `mat-raised-button` -> `mat-flat-button` in some cases)
- CSS class names changed (all `mat-` prefixes became `mdc-` internally)
- Custom CSS targeting Material internals will break
- Theming API changed from `@import` to `@use`:

```scss
// OLD (v14 and earlier)
@import '~@angular/material/theming';
@include mat-core();

// NEW (v15+)
@use '@angular/material' as mat;
```

- Density and typography APIs changed
- `mat-form-field` appearance options changed (`legacy` and `standard` removed, only `fill` and `outline` remain)

**The `ng update` schematic handles some of this automatically**, but custom styles targeting Material internals require manual fixes. Budget extra time for this step.

---

## Builder Migration: browser to application (v17 to v18)

If crossing the v17/v18 boundary, `ng update` migrates the builder in `angular.json`:

```json
// OLD
"builder": "@angular-devkit/build-angular:browser"

// NEW
"builder": "@angular-devkit/build-angular:application"
```

**Key impacts:**
- **Output path changes:** `dist/{app}/` becomes `dist/{app}/browser/` -- update Docker `COPY` commands
- **`fileReplacements` may break:** The `application` builder handles environment files differently. Check that `environment.ts` / `environment.prod.ts` substitution still works
- **Custom Webpack config breaks entirely:** If the project uses `@angular-builders/custom-webpack`, it's incompatible with the `application` builder. Either stay on `browser` builder or remove custom webpack config
- **`main` key renamed to `browser`** in angular.json options
- **`polyfills` becomes an array** of strings instead of a file path
- **Server-side rendering** gets auto-configured if `@angular/ssr` is detected

**If the project has a complex custom Webpack setup**, you may need to stay on the `browser` builder temporarily and migrate webpack customizations to the `application` builder's plugin system separately.

---

## HttpClientModule Deprecation (v18+)

Angular 18+ deprecates module-based HTTP setup. Migrate:

```typescript
// OLD (NgModule-based)
imports: [HttpClientModule]

// NEW (standalone or NgModule)
// In app.module.ts providers:
providers: [provideHttpClient(withInterceptorsFromDi())]

// Or in standalone bootstrap:
bootstrapApplication(AppComponent, {
  providers: [provideHttpClient(withInterceptorsFromDi())]
});
```

`withInterceptorsFromDi()` preserves existing class-based interceptors. Without it, interceptors stop working.

---

## Guards and Resolvers: Class to Functional (v15+)

Angular 15+ deprecated class-based guards in favor of functional guards:

```typescript
// OLD (class-based)
@Injectable({ providedIn: 'root' })
export class AuthGuard implements CanActivate {
  canActivate(): boolean { return this.authService.isLoggedIn(); }
}

// NEW (functional)
export const authGuard: CanActivateFn = () => {
  return inject(AuthService).isLoggedIn();
};
```

Class-based guards still work but will show deprecation warnings. The `ng update` schematic may migrate some automatically.

---

## karma.conf.js Coverage Reporter Replacement

If using `karma-coverage-istanbul-reporter`, replace with `karma-coverage`:

```javascript
// OLD
require('karma-coverage-istanbul-reporter'),

// NEW
require('karma-coverage'),
```

```javascript
// OLD
coverageIstanbulReporter: {
  dir: require('path').join(__dirname, './coverage/{app-name}'),
  reports: ['html', 'lcovonly', 'text-summary'],
  fixWebpackSourcePaths: true
},

// NEW
coverageReporter: {
  dir: require('path').join(__dirname, './coverage/{app-name}'),
  reporters: [
    { type: 'html' },
    { type: 'lcovonly' },
    { type: 'text-summary' }
  ]
},
```

---

## polyfills.ts Removal Steps

If `src/polyfills.ts` exists and only contains `import 'zone.js'`:

**Step 1:** Update `angular.json` build options:
```json
// OLD
"polyfills": ["src/polyfills.ts"]

// NEW
"polyfills": ["zone.js"]
```

**Step 2:** Remove from `tsconfig.app.json`'s `files` array:
```json
// OLD
"files": ["src/main.ts", "src/polyfills.ts"]

// NEW
"files": ["src/main.ts"]
```

**Step 3:** Delete `src/polyfills.ts`

**Edge case:** If `polyfills.ts` imports additional polyfills (e.g., `core-js`, `classlist.js`, `web-animations-js`), evaluate whether they're still needed for your browser targets. If targeting modern browsers only, they likely aren't.

---

## Legacy test.ts Removal

If `src/test.ts` exists (legacy test bootstrap), it can be removed since Angular 15+. The test polyfills are now configured directly in `angular.json`:

```json
"test": {
  "builder": "@angular-devkit/build-angular:karma",
  "options": {
    "polyfills": [
      "zone.js",
      "zone.js/testing"
    ]
  }
}
```

Remove `src/test.ts` and remove it from `tsconfig.spec.json`'s `files` array if referenced there.

### Jest Migration (Optional)

If migrating from Karma to Jest:

```bash
# Remove Karma
npm uninstall karma karma-chrome-launcher karma-coverage karma-jasmine karma-jasmine-html-reporter puppeteer

# Install Jest
npm install jest @angular-builders/jest @types/jest --save-dev
```

Update `angular.json` test builder:
```json
"test": {
  "builder": "@angular-builders/jest:run"
}
```

This is a significant change -- consider as separate future work unless specifically requested.

---

## TypeScript Config Detailed Settings

Angular's `ng update` schematics handle some of these automatically. Only change what hasn't been updated yet.

| Setting | Old Value | New Value | Notes |
|---------|-----------|-----------|-------|
| `target` | `"es5"` / `"es2015"` / `"es2017"` | `"ES2022"` | Angular 16+ requires ES2022 minimum |
| `module` | `"es2020"` / `"esnext"` | `"preserve"` | Modern Angular default |
| `moduleResolution` | `"node"` | `"bundler"` | Modern module resolution (Angular 17+) |
| `lib` | `["es2018", "dom"]` | `["ES2022", "dom"]` | Match target |
| `useDefineForClassFields` | `false` | Remove | Not needed unless you have property initializers depending on constructor injection order |
| `fullTemplateTypeCheck` | `true` | Remove | Deprecated -- replaced by `strictTemplates` |

**Angular compiler options:**
```json
"angularCompilerOptions": {
  "strictInjectionParameters": true,
  "strictTemplates": true
}
```

`strictTemplates` replaces the deprecated `fullTemplateTypeCheck`. If enabling for the first time, expect template type errors that need fixing:
- Add `| null` or `| undefined` to template bindings
- Use `$any()` as a temporary escape hatch for untyped third-party bindings
- Fix `*ngFor` / `@for` trackBy type mismatches

**Keep `experimentalDecorators: true`** -- still required by Angular for decorator-based DI.

---

## SCSS/Sass Processor Migration

If the project uses SCSS:

- **`node-sass` is dead.** The esbuild `application` builder does not support it. Switch to `sass` (Dart Sass):
  ```bash
  npm uninstall node-sass
  npm install sass --save-dev
  ```

- **`::ng-deep` is deprecated** (since Angular 14) but still works. It will eventually be removed. Flag for future refactoring but don't block the upgrade on it.

- **`/deep/` and `>>>` are removed.** If these exist in stylesheets, replace with `::ng-deep` or refactor to avoid piercing shadow DOM.

- **`@import` is deprecated in Dart Sass.** Replace with `@use` and `@forward`:
  ```scss
  // OLD
  @import 'variables';

  // NEW
  @use 'variables' as vars;
  // Reference: vars.$my-variable
  ```

---

## Angular Budget Updates

After major upgrades, bundle sizes often change. Check and update budgets in `angular.json`:

```json
"budgets": [
  {
    "type": "initial",
    "maximumWarning": "2mb",
    "maximumError": "5mb"
  },
  {
    "type": "anyComponentStyle",
    "maximumWarning": "6kb",
    "maximumError": "10kb"
  }
]
```

If the build fails with budget errors after upgrading, either:
1. Investigate why the bundle grew (did a tree-shakable import become non-tree-shakable?)
2. Increase the budget if the growth is expected (new Material MDC components are larger)

---

## Docker Runtime Env Injection Patterns

Many Angular Docker setups inject environment variables at container start time (not build time) using `sed` or `envsubst` on the built JavaScript files. This is because Angular bakes environment values into the bundle at build time, but production deploys need different values per environment.

**Common pattern (`start_container.sh`):**
```bash
#!/bin/bash
# Replace placeholders in built JS files with runtime env vars
sed -i "s|PLACEHOLDER_API_URL|${API_URL}|g" /usr/share/nginx/html/*.js
nginx -g 'daemon off;'
```

**If the output path changed** (from `dist/{app}/` to `dist/{app}/browser/`), update the `COPY` in the Dockerfile AND any `sed` paths in the startup script:

```dockerfile
# Make sure this matches the new output path
COPY --from=builder /app/dist/{app-name}/browser /usr/share/nginx/html
```

**If the project uses `environment.ts` file replacement** and migrated to the `application` builder, verify that `fileReplacements` in `angular.json` still works. The `application` builder handles this differently than the `browser` builder -- test with both `ng serve` (dev) and `ng build --configuration production` (prod).

---

## Angular Persistent Build Cache

Angular 17+ uses a persistent build cache (`.angular/cache/`). In CI, this can cause stale builds:

```yaml
# Option 1: Cache it for speed
- name: Cache Angular build
  uses: actions/cache@v4
  with:
    path: .angular/cache
    key: angular-cache-${{ hashFiles('package-lock.json') }}

# Option 2: Disable it in CI (safer)
# Set in angular.json:
# "cli": { "cache": { "enabled": false } }
```

**macOS Tahoe bug:** Angular's persistent cache uses `lmdb` which has a known crash on macOS 15 (Tahoe). If developers hit segfaults, disable the cache in `angular.json`:

```json
"cli": {
  "cache": {
    "enabled": false
  }
}
```

---

## Post-Upgrade Modernization (Future Work)

These are NOT part of the upgrade but should be documented as follow-up tickets:

### Standalone Components Migration
Angular strongly encourages standalone components over NgModules. If the app uses NgModule architecture, plan an incremental migration:
```bash
# Automated migration schematic
ng generate @angular/core:standalone
```
This is a large change -- do it as a separate effort, not during the version upgrade.

### Signal-based Reactivity
Angular promotes signals over classic RxJS `BehaviorSubject` patterns. Migrate incrementally:
```typescript
// OLD
private data$ = new BehaviorSubject<Data[]>([]);

// NEW
private data = signal<Data[]>([]);
```

### Zoneless Change Detection
Angular 18+ supports zoneless change detection. Optional migration:
```bash
ng generate @angular/core:zoneless-migration
```
This removes the `zone.js` dependency but requires signal-based reactivity throughout.

### ESLint Setup
If TSLint was removed without a replacement:
```bash
ng add @angular-eslint/schematics
```

### EOL Dependencies
Flag for replacement in future tickets:
- **CKEditor 4** (EOL June 2023) -> CKEditor 5 or Quill
- **moment.js** (maintenance-only) -> `date-fns` or native `Intl`/`Temporal`
- **Bootstrap 4** (EOL) -> Bootstrap 5 or remove in favor of Angular Material layout
