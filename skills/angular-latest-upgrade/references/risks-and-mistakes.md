# Angular Upgrade Risks and Common Mistakes

Reference tables for the Angular latest stable upgrade workflow. Consult these before and during each phase.

## Table of Contents

- [Risk Register](#risk-register)
- [Common Mistakes](#common-mistakes)

---

## Risk Register

| Risk | Mitigation |
|------|-----------|
| Third-party package blocks `ng update` with peer dep error | Use `--force` flag; update the blocking package first if possible |
| Skipping a major version misses automated migrations | Always upgrade one major at a time -- never skip |
| `strictTemplates` breaks build with template type errors | Fix incrementally -- use `$any()` as temporary escape hatch |
| Legacy Webpack builder not compatible with latest Angular | Migrate to `application` builder: `ng update` usually handles this automatically |
| `polyfills.ts` imports custom polyfills beyond `zone.js` | Audit imports before deleting -- move needed polyfills to `angular.json` polyfills array |
| `karma-coverage-istanbul-reporter` referenced but not installed | Replace with `karma-coverage` in karma.conf.js |
| Angular persistent cache crashes on macOS Tahoe | Disable cache in `angular.json`: `"cli": { "cache": { "enabled": false } }` |
| Docker output path changed with `application` builder | Angular 17+ outputs to `dist/{app}/browser/` not `dist/{app}/` |
| Node version mismatch between CI, Docker, and local dev | Standardize on Node LTS (22.x) everywhere |
| RxJS 6.x still in use | Must upgrade to RxJS 7.x before Angular 13+ -- run `npx ng update rxjs` |
| `test.ts` removal breaks test discovery | Ensure `angular.json` test config has `"polyfills": ["zone.js", "zone.js/testing"]` |
| Data providers not `static` in Jasmine 6 / Angular 21 | Refactor data providers to static methods |
| Material MDC migration (v15) breaks all custom Material CSS | Budget extra time -- audit all custom styles targeting Material internals |
| `@angular/cdk` version doesn't match `@angular/material` | Always update together -- `ng update @angular/material` handles both |
| Builder migration breaks custom Webpack config | Stay on `browser` builder or remove custom webpack; `application` builder uses different plugin system |
| `fileReplacements` breaks after builder migration | Test environment file substitution with both `ng serve` and `ng build --configuration production` |
| `HttpClientModule` removal breaks interceptors | Use `provideHttpClient(withInterceptorsFromDi())` to preserve class-based interceptors |
| `node-sass` fails with esbuild `application` builder | Replace with `sass` (Dart Sass): `npm uninstall node-sass && npm install sass --save-dev` |
| Runtime env injection (`sed` in start script) uses wrong path | Update paths after builder migration -- output moves to `dist/{app}/browser/` |
| Bundle budget errors after upgrade | Material MDC components are larger -- adjust budgets in angular.json if growth is expected |
| `@import` deprecated in Dart Sass | Replace with `@use`/`@forward` -- `@import` still works but emits warnings |

---

## Common Mistakes

| Mistake | Fix |
|---------|-----|
| Trying to jump multiple major versions at once | Run `ng update` for each major version sequentially |
| Running `ng update` without `--force` and getting stuck on peer deps | Use `--force` -- peer dep warnings are usually safe to bypass |
| Removing `puppeteer` when removing Protractor | Keep it if `karma-chrome-launcher` uses it for headless Chrome |
| Forgetting to update Docker output path after builder migration | Angular `application` builder outputs to `dist/{app}/browser/`, not `dist/{app}/` |
| Using Node odd-version (19, 21, 23) in Docker | Always use Node LTS (even versions: 18, 20, 22) |
| Enabling `strictTemplates` without fixing template errors | Fix or suppress errors before committing -- broken builds block team |
| Removing `polyfills.ts` without updating `angular.json` | Move polyfill references to `angular.json`'s `polyfills` array first |
| Not running `npm install` after `ng update` | `ng update` modifies `package.json` but may not install -- always follow with `npm install` |
| Committing with `git add -A` across phases | Add specific files per phase for atomic, rollback-friendly commits |
| Keeping deprecated `tslint.json` after removing TSLint | Delete the config file to avoid confusion |
| Using `npm install` instead of `npm ci` in Docker/CI | `npm ci` is faster and guarantees reproducible installs from lockfile |
| Ignoring `npm audit` results after upgrade | Run `npm audit` and resolve vulnerabilities before shipping |
| Removing `experimentalDecorators` from tsconfig | Angular still requires it for decorator-based DI -- keep it |
| Updating `@angular/material` without `@angular/cdk` | They must be the same version -- `ng update @angular/material` handles both |
| Not reviewing `ng update` schematic changes before committing | Schematics auto-modify templates and TS files -- review diffs for correctness before committing |
| Custom CSS targeting `mat-` internal classes after MDC migration | All internal classes changed -- audit custom Material styles |
| Removing `HttpClientModule` without `withInterceptorsFromDi()` | Interceptors silently stop working -- use `provideHttpClient(withInterceptorsFromDi())` |
| Keeping `node-sass` with `application` builder | Incompatible -- must switch to `sass` (Dart Sass) |
| Not updating `start_container.sh` paths after builder migration | Runtime env injection breaks if `sed` targets wrong output directory |
| Ignoring bundle budget failures | Investigate cause -- don't just increase limits without understanding why the bundle grew |
| Skipping `fileReplacements` testing after builder migration | Environment file substitution may silently stop working |
