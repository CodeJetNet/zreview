# Risks and Common Mistakes Reference

Known risks and pitfalls encountered during PHP 8.5 full stack upgrades.

## Table of Contents

- [Risk Register](#risk-register)
- [Common Mistakes](#common-mistakes)

---

## Risk Register

| Risk | Mitigation |
|------|-----------|
| Internal packages don't support PHP 8.5 | Audit before starting. File separate PRs as blockers. |
| Cache adapter incompatible with Predis 2.x | Replace with `symfony/cache` adapter (transitive dep via Doctrine) |
| Gedmo extensions break with ORM 3 | Pin `gedmo/doctrine-extensions: ^3.17` (added ORM 3 support) |
| `EntityManager` constructor changed in ORM 3 | Use `EntityManager::create()` as fallback |
| MySQL 8.4 stricter `ONLY_FULL_GROUP_BY` | Verify with test suite -- explicit column lists are safe |
| `lower_case_table_names` must be set at MySQL init | Configure via `command:` in docker-compose |
| Slim 4 no longer parses request bodies | Add `$app->addBodyParsingMiddleware()` |
| PHPUnit 12 mock notices | Use `createStub()` for deps without expectations |
| Firebase JWT v7 breaks QA token validation | Pin to `^6.11` -- do not upgrade to v7 |
| `tuupalo/slim-jwt-auth` or `jimtools/*` incompatible with Slim 4 | Replace with custom PSR-15 middleware using `firebase/php-jwt` |
| Redis used for Doctrine caching adds unnecessary hard dependency | Replace with `PhpFilesAdapter` + `NullAdapter` for dev |
| `composer.json` has `config.platform.php` set to old version | Update to `"8.5"` or remove -- otherwise Composer resolves deps for the wrong PHP version |
| PECL extensions fail to compile on PHP 8.5 | Check for beta/RC versions of the extension, or pin to a compatible version |
| `utf8mb4` indexes exceed InnoDB limit on `ROW_FORMAT=COMPACT` | MySQL 8.4 defaults to `DYNAMIC` -- verify with `information_schema.TABLES` query |
| Queue consumers / CLI scripts bootstrap the container differently | Audit all entry points in `bin/`, not just `public/index.php` |
| `DriverManager::getConnection($params, $config)` fails in DBAL 4 | DBAL 4 removed the second `$config` param -- pass only connection params |
| Raw SQL `prepare()` + `executeQuery(params)` silently drops params in DBAL 4 | `Statement::executeQuery()` takes zero args in DBAL 4 -- use `Connection::executeQuery($sql, $params)` or `Connection::executeStatement($sql, $params)` instead. Grep for `->prepare(` to find all instances. |

## Common Mistakes

| Mistake | Fix |
|---------|-----|
| Starting upgrades without behavioral baseline | Write Newman tests first -- they catch regressions unit tests miss |
| Skipping verification gates between phases | Each phase commit is a rollback point -- don't skip |
| Using `git add -A` for commits | Add specific files per phase to keep commits atomic |
| Forgetting body parsing middleware in Slim 4 | `$request->getParsedBody()` returns `null` without it |
| Using `createMock()` everywhere in PHPUnit 12 | Use `createStub()` for dependencies without expectations |
| Keeping `doctrine/annotations` with ORM 3 | Remove it -- ORM 3 uses PHP 8 attributes natively |
| Not updating charset in connection config | Change `'charset' => 'utf8'` to `'utf8mb4'` in DBAL config |
| Removing `notFoundHandler` without error middleware | Slim 4's `addErrorMiddleware()` replaces custom error handlers |
| Upgrading PHPUnit and Slim simultaneously | Upgrade PHPUnit first so tests are green before Slim migration |
| Upgrading Firebase JWT to v7 | Pin to `^6.11` -- v7 breaks token validation in QA |
| Keeping `tuupola/slim-jwt-auth` or `jimtools/*` | These are Slim 3-coupled or unmaintained -- replace with Firebase JWT PSR-15 middleware |
| Using Redis for Doctrine metadata/query cache | Use `PhpFilesAdapter` (prod) + `NullAdapter` (dev) -- eliminates Redis as a hard dependency |
| Forgetting to update `config.platform.php` in composer.json | Set to `"8.5"` or remove -- stale platform config causes wrong dependency resolution |
| Not adding `/cache/` to `.gitignore` | Doctrine proxy/cache dirs should never be committed |
| Missing `serverVersion` in DBAL connection params | Add `'serverVersion' => '8.4'` so DBAL generates correct SQL without a live connection |
| Only updating `public/index.php` but not CLI/queue entry points | Audit every file that bootstraps the container -- `bin/` scripts, task runners, cron jobs |
| Data providers not converted to `static` in PHPUnit 12 | PHPUnit 12 requires data providers to be `static` methods |
| Using `prepare()` + `executeQuery(params)` for raw SQL in DBAL 4 | `Statement::executeQuery()` takes zero args -- params are silently dropped, causing MySQL 1064 syntax errors at runtime. Use `Connection::executeQuery($sql, $params)` for SELECT, `Connection::executeStatement($sql, $params)` for write operations. Grep for `->prepare(` to audit. |
