# Technical Gotchas Reference

Hard-won lessons from real implementations. Consult this when working with these specific technologies.

## Table of Contents
- [Doctrine ORM 2→3 / DBAL 3→4](#doctrine-orm-23--dbal-34)
- [PHPUnit 9→12](#phpunit-912)
- [Composer in Docker](#composer-in-docker)
- [GitHub PAT Scopes](#github-pat-scopes)
- [PHP Operator Pitfalls](#php-operator-pitfalls)
- [PHPUnit Process Isolation + Xdebug](#phpunit-process-isolation--xdebug)

---

## Doctrine ORM 2→3 / DBAL 3→4

- `Doctrine\DBAL\Exception` became an **interface** in DBAL 4 — you cannot `throw new Exception()` with it. Use `RuntimeException` or `\Exception` for throw targets.
- `Doctrine\ORM\Exception\NotSupported` is deprecated in ORM 3 — remove all imports and `@throws` docblocks. Catch `\Throwable` at boundaries instead.
- `Doctrine\Common\Proxy\AbstractProxyFactory` moved to `Doctrine\ORM\Proxy\ProxyFactory`.
- Entity ORM attributes must use **named parameters** in ORM 3 (positional was silently allowed in ORM 2). E.g., `#[ORM\ManyToOne("Batches")]` must become `#[ORM\ManyToOne(targetEntity: Batches::class)]`.
- `symfony/var-exporter` v8 removed `LazyGhostTrait` — pin to `^7.2` for Doctrine ORM 3 until Doctrine supports PHP 8.4 native lazy objects natively.

## PHPUnit 9→12

- `->willReturn(null)` on void methods is now an error — remove the `willReturn(null)` call entirely.
- `$this->onConsecutiveCalls()` is removed — use `willReturnCallback()` with a counter and `match` expression.
- `createMock()` without `expects()` triggers notices — use `createStub()` for dependencies that aren't being verified.
- Remove all deprecated xml attributes from phpunit.xml: `backupGlobals`, `backupStaticAttributes`, `convertErrorsToExceptions`, `convertNoticesToExceptions`, `convertWarningsToExceptions`, etc.
- `<coverage>` element replaced by `<source>`. Coverage reports moved to CLI flags.

## Composer in Docker

### VCS repos + SSH won't work
Docker containers don't have SSH keys. When running `composer install/update` inside containers with VCS repos that use `git@github.com:`, configure git to redirect SSH to HTTPS:
```bash
git config --global url."https://github.com/".insteadOf "git@github.com:"
composer config --global github-oauth.github.com $TOKEN
```

### Dependency conflict resolution
When `composer update` fails with conflicts, read the full error. Common patterns:
- Package A needs library v5, Package B needs library v6 → check if Package A is actually used. If unused, remove it.
- Security advisory blocking install → add audit ignore config in composer.json.
- Lock file stale → use `composer update`, not `composer install`.

Before changing any version constraint, trace the full dependency tree: `composer why <package>`, `composer info -a <package> <version>`, check what each transitive dep requires. Build a complete picture of what will cascade, then make all changes at once.

### minimum-stability: dev
When a `composer.json` has `"minimum-stability": "dev"` and `"prefer-stable": true`, composer may still install dev versions (e.g., `6.x-dev` instead of `v6.11.1`) if the version constraint allows it. Use tilde constraints like `~6.11.0` to pin to stable patch ranges, or explicitly check the resolved version after `composer update` with `composer show <package>`.

## GitHub PAT Scopes

- Pushing changes to `.github/workflows/` requires the `workflow` scope on the PAT
- `gh auth login` requires `read:org` scope
- If push fails with "refusing to allow a Personal Access Token", check scope — don't retry blindly
- The token is in `~/.zshrc` as `GITHUB_PERSONAL_ACCESS_TOKEN` — source it with `source ~/.zshrc`

## PHP Operator Pitfalls

### `??` doesn't catch `false`
`getenv()` returns `false` when unset, not `null`. `$var ?? null` won't catch `false`. Use `$var ?: null` when the source can return falsy values like `false`, `''`, or `0`. Remember: `??` checks for `null` only. `?:` checks for any falsy value.

### Cast before `??` loses the null check
`(float)$data['key'] ?? 0.00` — the `(float)` cast executes first (triggering the undefined key warning), then `??` never fires because the expression already evaluated to a float. Fix: `(float)($data['key'] ?? 0.00)` — parentheses force `??` to evaluate first.

## PHPUnit Process Isolation + Xdebug

PHPUnit's `@runInSeparateProcess` captures stderr from the child process. Xdebug writes connection warnings to stderr, which PHPUnit treats as test output and flags as an error. Either disable Xdebug for test runs (`php -d xdebug.mode=off`) or avoid `@runInSeparateProcess`. For singleton reset problems, prefer restructuring the test to avoid needing process isolation.
