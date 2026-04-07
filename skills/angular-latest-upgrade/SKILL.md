---
name: angular-latest-upgrade
description: "Upgrade Angular apps to latest stable version. TRIGGER on: 'upgrade Angular', 'update Angular', 'Angular migration', 'ng update', 'Angular version upgrade', 'bump Angular', 'modernize Angular app', 'Angular 14/15/16/17/18/19/20/21 upgrade', 'migrate Angular Material', 'remove Protractor', 'remove TSLint from Angular'. Covers sequential ng update migrations, deprecated tooling removal, TypeScript modernization, test config updates, Docker/CI, and post-upgrade modernization paths."
---

# Angular Latest Stable Upgrade Guide

## Overview

Phased migration strategy for upgrading Angular applications to the latest stable version. Angular requires upgrading **one major version at a time** using `ng update` schematics. Each phase has a verification gate before proceeding.

**Core principle:** Baseline first, sequential `ng update` one major at a time, deprecated tooling removal, dependency updates, config modernization, Docker/CI last. Never skip verification gates.

## When to Use

- Upgrading an Angular application across one or more major versions
- Removing deprecated tooling (Protractor, TSLint, karma-coverage-istanbul-reporter)
- Modernizing TypeScript/tsconfig for latest Angular
- Updating test configuration (Karma/Jasmine, or migrating to Jest)
- Updating Docker/CI for current Node LTS
- Any combination of the above

**When NOT to use:**
- Greenfield Angular projects (use `ng new` with latest)
- Non-Angular frontend upgrades (React, Vue, etc.)
- Minor/patch version bumps within the same major

## Phase Order

1. Discovery: Full Repo Review
2. Generate Repo-Specific Plan
3. Plan Self-Verification (loop until no discrepancies)
4. Pre-flight: Dependency Compatibility
5. Phase 1: Behavioral Baseline
6. Phase 2: Unit Test Coverage
7. Phase 3: Sequential `ng update`
8. Phase 4: Remove Deprecated Tooling
9. Phase 5: Update npm Dependencies
10. Phase 6: Modernize Test Config
11. Phase 7: Remove Legacy Files
12. Phase 8: Modernize TypeScript Config
13. Phase 9: Docker and CI Updates
14. Phase 10: Final Verification

**Every phase ends with: build, test, commit.** Each phase gets its own commit for easy rollback.

---

## Discovery: Full Repository Review

**Before writing any plan, you MUST fully understand the repository.** Do not skip or shortcut this phase.

### Step 1: Understand the Architecture

Read and analyze the following (adapt to what exists in the repo):

- `package.json` -- all dependencies with versions, scripts, engines
- `angular.json` -- project config, builders, budgets, assets, styles, polyfills
- `tsconfig.json`, `tsconfig.app.json`, `tsconfig.spec.json` -- TypeScript config
- `karma.conf.js` or `jest.config.ts` -- test runner configuration
- `Dockerfile` -- Node version, nginx version, build stages
- `docker-compose.yml` -- if it exists
- `.github/workflows/` or CI config -- build, test, deploy pipelines
- `src/main.ts` -- bootstrap method (standalone vs NgModule)
- `src/app/app.module.ts` -- if it exists (NgModule-based)
- `src/app/app.config.ts` -- if it exists (standalone-based)
- `src/app/app-routing.module.ts` or `src/app/app.routes.ts` -- routing config
- `src/polyfills.ts` -- if it exists (legacy, removable since Angular 15)
- `src/test.ts` -- if it exists (legacy test bootstrap, removable)
- `src/environments/` -- environment configs
- `e2e/` -- end-to-end test config (Protractor? Cypress? Playwright?)
- `tslint.json` -- if it exists (deprecated, should be replaced with ESLint)
- `.browserslistrc` or `browserslist` in package.json -- browser targets
- `.editorconfig` -- code style

### Step 2: Map the Application Architecture

Document: bootstrap method (NgModule vs Standalone), component style, routing approach, HTTP setup, forms, state management, UI framework, third-party integrations, test framework, and build system (Webpack legacy vs esbuild/application modern).

### Step 3: Identify Current Versions and Upgrade Path

Create a version inventory and determine how many major versions to cross:

| Component | Current Version | Target Version | Majors to Cross |
|-----------|----------------|----------------|-----------------|
| @angular/* | ? | latest stable | ? |
| @angular/material | ? | latest stable | ? |
| @angular/cdk | ? | latest stable | ? |
| TypeScript | ? | (auto via ng update) | - |
| Node.js | ? | LTS (22.x) | ? |
| RxJS | ? | 7.8.x | ? |
| zone.js | ? | 0.15.x | ? |
| (each significant package) | ? | ? | ? |

**Use the Angular Update Guide:** Reference https://angular.dev/update-guide for version-specific migration steps and breaking changes.

### Step 4: Generate the Repo-Specific Upgrade Plan

Write a detailed, repo-specific upgrade plan to `docs/plans/YYYY-MM-DD-angular-XX-upgrade.md` that:

- References **actual file paths** in this repo
- Lists **exact dependency versions** currently in `package.json`
- Names **specific components, services, and modules** that need migration
- Identifies **repo-specific risks** (third-party package compatibility, custom webpack config, etc.)
- Follows the phase structure from this guide but tailored to what this repo actually uses
- Calculates the **exact number of `ng update` steps** needed (one per major version)

**Skip phases that don't apply.** If the repo doesn't use Protractor, skip that removal. If it's already on the latest TypeScript config patterns, skip Phase 8.

---

## Plan Self-Verification

**After generating the plan, you MUST verify it before proceeding.** Do not skip this step.

### Step 1: Find Discrepancies

Review the plan in totality and actively look for:
- Dependencies referenced that aren't in `package.json`
- Files referenced that don't exist
- `ng update` steps that skip a major version (must be sequential)
- Third-party packages that may not support the target Angular version
- Build config changes that don't match the actual `angular.json` structure
- Missing components or services that would be affected by breaking changes

### Step 2: Generate Verification Questions

Generate 3-5 verification questions that would expose errors in the plan. Examples:

- "Does the plan account for every file that imports from `@angular/http` (removed in v15)?"
- "Are there third-party packages with Angular version peer dependencies that would block `ng update`?"
- "Does the repo use the legacy Webpack builder or the modern application/esbuild builder?"
- "Are there lazy-loaded modules that need route config updates?"
- "Does the karma.conf.js reference packages that aren't in package.json?"

### Step 3: Answer Each Question Independently

For each question, **go back to the codebase** and verify. Do not answer from memory or assumption. Use grep, glob, and file reads to confirm.

### Step 4: Revise the Plan

Update the repo-specific plan based on findings. If no changes needed, explicitly state "Plan verified -- no discrepancies found."

**Only proceed to implementation after this verification loop passes.**

---

## Pre-Flight: Dependency Compatibility

Before starting, check third-party package compatibility with the target Angular version.

```bash
npm ls 2>&1 | grep "ERESOLVE\|peer dep\|invalid"
npx ng update
```

**Common blockers:**
- Third-party UI libraries with Angular version peer deps (CKEditor, ngx-*, primeng)
- State management libraries (NgRx version must match Angular major)
- Custom builders or schematics with version constraints

If any package blocks the upgrade, determine if:
1. A compatible version exists (check npm/GitHub)
2. The package can be replaced (e.g., CKEditor 4 -> CKEditor 5)
3. The `--force` flag can safely bypass the peer dep (last resort)

---

## Phase 1: Behavioral Baseline

Before changing anything, establish what "working" looks like.

**If the app has E2E tests (Cypress/Playwright):** Run the full E2E suite and confirm all pass.

**If no E2E tests exist:** Create a manual QA checklist covering every route and key user flows.

**Always:**
- Run the existing unit test suite: `npm test` or `npx ng test --watch=false`
- Run the production build: `npm run build` or `npx ng build --configuration production`
- Document any pre-existing failures or warnings

### Verification Gate

```bash
npx ng build --configuration production
npx ng test --watch=false
```

All passing. Commit any baseline fixes.

---

## Phase 2: Expand Unit Test Coverage

Identify untested components and services. Add tests **in the current Angular version** so they pass now.

**Priority targets:** Components without `.spec.ts` files, services with complex logic or HTTP calls, interceptors, guards, resolvers, pipes with transformation logic.

### Verification Gate

```bash
npx ng test --watch=false --code-coverage
```

All green. Commit new tests.

---

## Phase 3: Sequential `ng update` (One Major at a Time)

**Angular REQUIRES upgrading one major version at a time.** Each major version has automated schematics that apply code migrations. Skipping versions means missing these migrations.

### For Each Major Version Step (repeat N -> N+1 until target):

```bash
# Step 1: Update Angular core and CLI
npx ng update @angular/core@{N+1} @angular/cli@{N+1} --force

# Step 2: Update Angular Material (if used)
npx ng update @angular/material@{N+1} --force

# Step 3: Install and verify
npm install
npx ng build --configuration production
npx ng test --watch=false

# Step 4: Review schematic changes
git diff  # Review ALL changes before committing

# Step 5: Commit
git add -A
git commit -m "chore: upgrade Angular {N} -> {N+1} via ng update schematics"
```

**Order matters:** Always update `@angular/core` + `@angular/cli` BEFORE `@angular/material`. Material depends on core.

### Common `ng update` Schematics by Version

| Version | Key Automated Migrations |
|---------|------------------------|
| 14 -> 15 | Standalone component support, `RouterModule` -> `provideRouter` (optional), Material MDC migration. See `references/migration-patterns.md` section "Angular Material MDC Migration" |
| 15 -> 16 | `DestroyRef`, required inputs signal, class-based guards -> functional guards. See `references/migration-patterns.md` section "Guards and Resolvers" |
| 16 -> 17 | Control flow (`@if`, `@for`, `@switch`), deferrable views |
| 17 -> 18 | Builder migration (Webpack -> application/esbuild), `HttpClientModule` -> `provideHttpClient()`. See `references/migration-patterns.md` sections "Builder Migration" and "HttpClientModule Deprecation" |
| 18 -> 19 | Incremental hydration, resource API |
| 19 -> 20 | `InjectFlags` removal, `TestBed.get` -> `TestBed.inject` |
| 20 -> 21 | `ngClass` -> `class` bindings, `ngStyle` -> `style`, `SimpleChanges` generic |

**Not all migrations are applied automatically.** Check the Angular Update Guide for manual steps required at each version.

### Edge Cases

**Peer dependency conflicts during `ng update`:**
1. `--force` flag (usually sufficient)
2. Update the blocking package first: `npm install blocking-package@compatible-version`
3. Temporarily remove the blocking package, run `ng update`, then re-add it

**TypeScript version conflicts:** `ng update` handles TypeScript automatically. If not, manually install: `npm install typescript@~{version} --save-dev`

**RxJS compatibility:**
- Angular 13-16: RxJS 7.x required
- Angular 17+: RxJS 7.x still supported, RxJS 8 not yet required
- If on RxJS 6.x, run `npx ng update rxjs` before upgrading Angular

### Verification Gate

After **each** major version step:

```bash
npx ng build --configuration production
npx ng test --watch=false
```

Both must pass before proceeding to the next major version.

---

## Phase 4: Remove Deprecated Tooling

### Protractor (deprecated since 2023, removed from Angular CLI)

```bash
npm uninstall protractor @types/jasminewd2 jasmine-spec-reporter ts-node
rm -rf e2e/
```

In `angular.json`, remove the `"e2e"` architect target. In `package.json`, remove `"e2e"` script.

**Note:** Keep `puppeteer` if it's used by `karma-chrome-launcher` for headless Chrome in unit tests.

### TSLint (deprecated since 2019, builder removed from Angular CLI)

```bash
npm uninstall tslint codelyzer
rm tslint.json
```

In `angular.json`, remove the `"lint"` architect target if it uses `@angular-devkit/build-angular:tslint`. In `package.json`, remove `"lint"` script.

### karma-coverage-istanbul-reporter

If referenced but not in `package.json` (common ghost dependency), or if installed -- replace with `karma-coverage`. See `references/migration-patterns.md` section "karma.conf.js Coverage Reporter Replacement" for code patterns.

### Verification Gate

```bash
npx ng build --configuration production
npx ng test --watch=false
```

---

## Phase 5: Update Remaining npm Dependencies

**Production:** `rxjs` 7.8.x (RxJS 8 not yet required), `zone.js` 0.15.x, `tslib` latest 2.x, `file-saver` latest 2.x. Note EOL deps: `bootstrap` 4.x (EOL, plan 5.x), `ckeditor4-angular` (EOL June 2023), `moment` (maintenance-only).

**Dev:** `@types/jasmine` (match jasmine-core major), `@types/node` (match Node LTS 22.x), `jasmine-core` 6.x, `karma` 6.x, `karma-chrome-launcher` 3.x, `karma-coverage` 2.x, `karma-jasmine` 5.x, `karma-jasmine-html-reporter` 2.x, `puppeteer` 24.x.

### Verification Gate

```bash
npm audit
npx ng build --configuration production
npx ng test --watch=false
```

---

## Phase 6: Modernize Test Config

Replace deprecated `karma-coverage-istanbul-reporter` with `karma-coverage`, remove legacy `src/test.ts`. See `references/migration-patterns.md` sections "karma.conf.js Coverage Reporter Replacement" and "Legacy test.ts Removal" for detailed code patterns.

### Verification Gate

```bash
npx ng test --watch=false
```

---

## Phase 7: Remove Legacy Files

Remove `src/polyfills.ts` (if only `zone.js` import), update `.browserslistrc` to modern browsers only. See `references/migration-patterns.md` section "polyfills.ts Removal Steps" for the step-by-step process.

### .browserslistrc Modernization

```
last 2 Chrome versions
last 1 Firefox version
last 2 Edge major versions
last 2 Safari major versions
last 2 iOS major versions
Firefox ESR
```

Remove any IE references -- Angular dropped IE support in v13.

### Verification Gate

```bash
npx ng build --configuration production
npx ng test --watch=false
```

---

## Phase 8: Modernize TypeScript Config

Update `tsconfig.json` settings and SCSS processor. See `references/migration-patterns.md` sections "TypeScript Config Detailed Settings", "SCSS/Sass Processor Migration", and "Angular Budget Updates" for detailed settings tables and code patterns.

### Verification Gate

```bash
npx ng build --configuration production
npx ng test --watch=false
```

If `strictTemplates` causes build failures, fix template type errors before proceeding.

---

## Phase 9: Docker and CI Updates

### Dockerfile

```dockerfile
# Build stage -- use Node LTS
FROM node:22-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build -- --configuration production

# Production stage -- use current nginx
FROM nginx:1.27-alpine
COPY --from=builder /app/dist/{app-name}/browser /usr/share/nginx/html
```

**Key updates:**
- Node version: use current LTS (22.x), not a non-LTS odd version
- nginx: update to `1.27-alpine` (or latest stable)
- Use `npm ci` instead of `npm install` for reproducible builds
- **Output path:** Angular 17+ with the `application` builder outputs to `dist/{app-name}/browser/` (not `dist/{app-name}/`)

For runtime environment injection patterns and persistent build cache configuration, see `references/migration-patterns.md` sections "Docker Runtime Env Injection Patterns" and "Angular Persistent Build Cache".

### CI Workflow Updates

```yaml
- name: Setup Node
  uses: actions/setup-node@v4
  with:
    node-version: '22'
    cache: 'npm'
```

**Node version alignment:** Ensure CI, Docker, and local development all use the same Node LTS major version.

### Verification Gate

```bash
rm -rf node_modules dist .angular
npm ci
npx ng build --configuration production
npx ng test --watch=false
docker build -t {app-name}-test .
```

---

## Phase 10: Final Verification

Clean slate rebuild from scratch:

```bash
rm -rf node_modules dist .angular package-lock.json
npm install
npx ng build --configuration production
npx ng test --watch=false --code-coverage
npx ng serve  # Smoke test: app loads, key features work, no console errors
npm audit
docker build -t {app-name}-test .
docker run -p 8080:80 {app-name}-test  # Verify at http://localhost:8080
```

**Expected:** Build succeeds, all tests pass, no security vulnerabilities, Docker image builds and serves correctly.

---

## Quick Reference: File Change Map

| File | What Changes |
|------|-------------|
| `package.json` | All dependency versions, remove deprecated scripts |
| `package-lock.json` | Regenerated |
| `angular.json` | Builder config, polyfills, remove e2e/lint targets, budget updates |
| `tsconfig.json` | `target`, `module`, `moduleResolution`, `lib`, Angular compiler options |
| `tsconfig.app.json` | Remove `polyfills.ts` from `files` |
| `tsconfig.spec.json` | Remove `test.ts` from `files` if referenced |
| `karma.conf.js` | Replace coverage reporter, update plugins |
| `src/polyfills.ts` | **Delete** -- replaced by `"polyfills": ["zone.js"]` in angular.json |
| `src/test.ts` | **Delete** -- replaced by polyfills in angular.json test config |
| `e2e/` | **Delete** -- Protractor removed |
| `tslint.json` | **Delete** -- TSLint deprecated |
| `Dockerfile` | Node LTS, nginx version, output path |
| `.github/workflows/*.yml` | Node version, checkout@v4, setup-node@v4 |
| `.browserslistrc` | Modern browsers only |
| `src/app/**/*.ts` | Automated migrations from `ng update` schematics |
| `src/app/**/*.html` | Control flow migration if applied (`*ngIf` -> `@if`, etc.) |

---

## Reference Files

For detailed code examples, migration patterns, and risk tables:

- **`references/migration-patterns.md`** -- All detailed code examples: Material MDC migration, builder migration, HttpClient migration, functional guards, karma.conf.js patterns, polyfills.ts removal, TypeScript config settings, SCSS/Sass migration, budget updates, Docker env injection, build cache, and post-upgrade modernization paths (standalone, signals, zoneless, ESLint, EOL deps).
- **`references/risks-and-mistakes.md`** -- Full Risk Register (21 risks with mitigations) and Common Mistakes table (21 mistakes with fixes).
