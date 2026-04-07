# Migration Patterns Reference

Detailed code examples for each migration pattern referenced by the main SKILL.md guide.

## Table of Contents

- [Slim 3 to Slim 4 Patterns](#slim-3-to-slim-4-patterns)
  - [1. Container: Slim 3 Pimple to PHP-DI](#1-container-slim-3-pimple-to-php-di)
  - [2. Service Definitions: Pimple Closures to PHP-DI Array](#2-service-definitions-pimple-closures-to-php-di-array)
  - [3. App Bootstrap: Slim App to DI Bridge](#3-app-bootstrap-slim-app-to-di-bridge)
  - [4. Middleware: Closures to PSR-15 MiddlewareInterface](#4-middleware-closures-to-psr-15-middlewareinterface)
  - [5. Controllers: PSR-7 Interfaces, Remove withJson](#5-controllers-psr-7-interfaces-remove-withjson)
  - [6. Route Callable: String to Class Reference](#6-route-callable-string-to-class-reference)
  - [7. Body Parsing Middleware](#7-body-parsing-middleware)
  - [8. Route Groups with Middleware](#8-route-groups-with-middleware)
  - [9. Custom Error Handlers](#9-custom-error-handlers)
  - [10. CORS Middleware](#10-cors-middleware)
  - [11. Queue Consumers and CLI Entry Points](#11-queue-consumers-and-cli-entry-points)
  - [Test Updates for Slim 4](#test-updates-for-slim-4)
- [Doctrine ORM 2 to 3 / DBAL 3 to 4 Patterns](#doctrine-orm-2-to-3--dbal-3-to-4-patterns)
  - [1. EntityManager Creation](#1-entitymanager-creation)
  - [2. Annotations to Attributes](#2-annotations-to-attributes)
  - [3. Migration Files: Remove Platform Checks](#3-migration-files-remove-platform-checks)
  - [4. Gedmo Extensions](#4-gedmo-extensions)
  - [5. Caching: PSR-6 Required, Prefer PhpFilesAdapter](#5-caching-psr-6-required-prefer-phpfilesadapter)
  - [6. Doctrine CLI Config](#6-doctrine-cli-config)
  - [7. doctrine/data-fixtures Compatibility](#7-doctrinedata-fixtures-compatibility)
  - [8. getReference Requires class-string](#8-getreference-requires-class-string)
  - [9. Raw SQL: Statement executeQuery No Longer Accepts Parameters](#9-raw-sql-statement-executequery-no-longer-accepts-parameters)
- [JWT Middleware Replacement Pattern](#jwt-middleware-replacement-pattern)
- [Database Charset Migration Pattern](#database-charset-migration-pattern)

---

## Slim 3 to Slim 4 Patterns

### 1. Container: Slim 3 Pimple to PHP-DI

```php
// OLD: bootstrap.php (Slim 3)
$settings = require_once "config/settings.php";
return new \Slim\Container($settings);

// NEW: bootstrap.php (Slim 4 + PHP-DI)
$containerBuilder = new \DI\ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/config/container.php');
return $containerBuilder->build();
```

### 2. Service Definitions: Pimple Closures to PHP-DI Array

```php
// OLD: dependencies.php (Pimple)
$container['MyService'] = function ($c) {
    return new MyService($c['Repository'], $c['Publisher']);
};

// NEW: config/container.php (PHP-DI)
return [
    'MyService' => function (ContainerInterface $c) {
        return new MyService($c->get('Repository'), $c->get('Publisher'));
    },
    ResponseFactoryInterface::class => fn () => new ResponseFactory(),
];
```

### 3. App Bootstrap: Slim App to DI Bridge

```php
// OLD: public/index.php
$app = new \Slim\App($container);
$app->add($middlewareClosure);
$app->get('/path', 'Controller:method');
$app->run();

// NEW: public/index.php
$container = require __DIR__ . '/../bootstrap.php';
$app = \DI\Bridge\Slim\Bridge::create($container);
$app->addBodyParsingMiddleware();  // Slim 4 doesn't parse bodies automatically
$app->addRoutingMiddleware();
$app->addErrorMiddleware($displayErrors, true, true);

$app->get('/path', ControllerClass::class);
$app->run();
```

Remove `notFoundHandler` / `notAllowedHandler` closures -- Slim 4 error middleware handles these.

### 4. Middleware: Closures to PSR-15 MiddlewareInterface

```php
// OLD: Slim 3 double-pass
public function __invoke($request, $response, $next) {
    return $next($request, $response);
}

// NEW: PSR-15 single-pass
class MyMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}
```

Key changes:
- Inject `ResponseFactoryInterface` -- never `new Response()` directly
- Replace `$next($request, $response)` with `$handler->handle($request)`
- Extract inline JWT/auth closures into proper middleware classes

### 5. Controllers: PSR-7 Interfaces, Remove withJson

```php
// OLD (Slim 3)
use Slim\Http\Request;
use Slim\Http\Response;
return $response->withJson(['data' => $result], 200);

// NEW (Slim 4)
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
$response->getBody()->write(json_encode(['data' => $result]));
return $response->withHeader('Content-Type', 'application/json');
```

### 6. Route Callable: String to Class Reference

```php
// OLD: $app->get('/path', 'Controller:method')
// NEW: $app->get('/path', ControllerClass::class)  // Uses __invoke
```

### 7. Body Parsing Middleware

Slim 4 does not parse request bodies automatically. If your app reads `$request->getParsedBody()`, you must add:

```php
$app->addBodyParsingMiddleware(); // Add before routing middleware
```

Without this, `getParsedBody()` returns `null` for JSON/form POST bodies.

### 8. Route Groups with Middleware

```php
// OLD (Slim 3)
$app->group('/api', function () use ($app) {
    $app->get('/orders/{guid}', 'GetOrder:__invoke');
    $app->post('/orders', 'CreateOrder:__invoke');
})->add($jwtMiddleware);

// NEW (Slim 4)
$app->group('/api', function ($group) {
    $group->get('/orders/{guid}', GetOrder::class);
    $group->post('/orders', CreateOrder::class);
})->add(JwtMiddleware::class);  // Resolved from container
```

### 9. Custom Error Handlers

Slim 3's `$container['errorHandler']`, `$container['notFoundHandler']`, and `$container['notAllowedHandler']` are removed. Replace with Slim 4's error middleware:

```php
// OLD (Slim 3)
$container['errorHandler'] = function ($c) {
    return function ($request, $response, $exception) { /* ... */ };
};

// NEW (Slim 4) -- custom error renderer
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    ServerRequestInterface $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode([
        'error' => $displayErrorDetails ? $exception->getMessage() : 'Internal Server Error'
    ]));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
});
```

### 10. CORS Middleware

If the app serves cross-origin requests, add CORS middleware. In Slim 3, this was often an inline closure. In Slim 4, create a proper PSR-15 class:

```php
class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response
            ->withHeader('Access-Control-Allow-Origin', getenv('CORS_ORIGIN') ?: '*')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }
}
```

Add it to the app **before** routing middleware: `$app->add(CorsMiddleware::class);`

Handle preflight `OPTIONS` requests with a dedicated route or by checking the method in middleware.

### 11. Queue Consumers and CLI Entry Points

If the repo has CLI commands or queue consumers (e.g., AMQP task runners), they may also bootstrap the container and use Slim 3 types. Check:
- `bin/` scripts
- Any file that does `require 'bootstrap.php'` outside of `public/index.php`
- Queue consumer entry points that reference the container

These need the same Slim 3 to PHP-DI container updates but do NOT need routing/middleware changes.

### Test Updates for Slim 4

- Mock `ServerRequestInterface` and `ResponseInterface` (PSR-7) instead of `Slim\Http\*`
- Mock `StreamInterface` and wire it to `getBody()`
- Mock `RequestHandlerInterface` for PSR-15 middleware tests
- Replace `withJson()` expectations with `getBody()->write()` assertions

---

## Doctrine ORM 2 to 3 / DBAL 3 to 4 Patterns

### 1. EntityManager Creation

Replace `ORMSetup` shorthand with explicit `Configuration` setup. Use `PhpFilesAdapter` for caching (no Redis dependency). Use `NullAdapter` in development to avoid stale cache during iteration.

```php
// OLD (ORM 2)
$config = Setup::createAnnotationMetadataConfiguration($paths, $isDevMode);
$config->setAutoGenerateProxyClasses(AbstractProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);
$entityManager = EntityManager::create($connectionParams, $config);

// NEW (ORM 3 -- explicit Configuration, no Redis)
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Proxy\ProxyFactory;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

$config = new Configuration();

$queryCache = new PhpFilesAdapter('query', 0, '/app/cache/doctrine');
$metadataCache = new PhpFilesAdapter('metadata', 0, '/app/cache/doctrine');
$autoGenerate = ProxyFactory::AUTOGENERATE_NEVER;

if (getenv('ENVIRONMENT') === 'development') {
    $autoGenerate = ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED;
    $queryCache = new NullAdapter();
    $metadataCache = new NullAdapter();
}

$config->setMetadataCache($metadataCache);
$config->setMetadataDriverImpl(new AttributeDriver(['/app/src/Entities'], true));
$config->setQueryCache($queryCache);
$config->setProxyDir('/app/cache/doctrine/proxy');
$config->setProxyNamespace('App\\EntityProxies');
$config->setAutoGenerateProxyClasses($autoGenerate);

$connection = DriverManager::getConnection($dbParams);  // DBAL 4: NO $config param
$entityManager = new EntityManager($connection, $config);
```

**DBAL 4 breaking change:** `DriverManager::getConnection()` no longer accepts a `Configuration` object as the second parameter. Pass only the connection params array.

**`serverVersion` in connection params:** Add `'serverVersion' => '8.4'` to your connection params so DBAL generates MySQL 8.4-compatible SQL without needing a live connection to detect the version:

```php
$dbParams = [
    'driver' => 'pdo_mysql',
    'host' => getenv('MYSQL_DB_HOST'),
    'user' => getenv('MYSQL_DB_USERNAME'),
    'password' => getenv('MYSQL_DB_PASSWORD'),
    'dbname' => getenv('MYSQL_DB_DATABASE'),
    'charset' => 'utf8mb4',
    'serverVersion' => '8.4',
];
```

**Why PhpFilesAdapter over Redis:** Removes Redis as a hard dependency for Doctrine caching. File-based caching is fast enough for metadata/query caches and eliminates a failure point. Use `NullAdapter` in development so cache never goes stale during iteration.

### 2. Annotations to Attributes

```php
// OLD (Annotations)
/** @ORM\Entity @ORM\Table(name="orders") */
class Order {
    /** @ORM\Column(type="string") */
    private string $guid;
}

// NEW (Attributes)
#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order {
    #[ORM\Column(type: 'string')]
    private string $guid;
}
```

### 3. Migration Files: Remove Platform Checks

`getDatabasePlatform()->getName()` is removed in DBAL 4. Remove `$this->abortIf(...)` platform guards from migration files -- they're unnecessary if you always target one database.

### 4. Gedmo Extensions

`gedmo/doctrine-extensions: ^3.17` supports ORM 3 and PHP 8 attributes natively. If annotation-style imports break, switch to attribute syntax:

```php
use Gedmo\Mapping\Annotation\Timestampable;

#[Timestampable(on: "create")]
private ?\DateTime $createdAt = null;
```

### 5. Caching: PSR-6 Required, Prefer PhpFilesAdapter

ORM 3 requires PSR-6 `CacheItemPoolInterface`. **Prefer `PhpFilesAdapter`** over `RedisAdapter` -- it removes Redis as a hard dependency for Doctrine and is fast enough for metadata/query caches. If the existing EntityManagerFactory uses Redis for Doctrine caching, remove it and switch to `PhpFilesAdapter`. Use `NullAdapter` in development to prevent stale cache.

```php
// Production
$cache = new PhpFilesAdapter('doctrine', 0, '/app/cache/doctrine');

// Development -- no caching, no stale state
$cache = new NullAdapter();
```

### 6. Doctrine CLI Config

If the repo has a `cli-config.php` or `config/cli-config.php` for Doctrine CLI tools, update it to use the new EntityManager factory:

```php
// cli-config.php
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

$entityManager = require __DIR__ . '/bootstrap-em.php'; // Your EntityManager factory

return ConsoleRunner::createHelperSet($entityManager);
// OR for Doctrine ORM 3:
return new SingleManagerProvider($entityManager);
```

If the repo uses `doctrine/migrations`, check `migrations.php` or `migrations.yaml` config for deprecated options.

### 7. doctrine/data-fixtures Compatibility

If the repo uses `doctrine/data-fixtures`, check compatibility with ORM 3:
- `^1.5` may NOT work with ORM 3 -- upgrade to `^2.0` if needed
- Fixture classes may need `load(ObjectManager $manager)` signature updates

### 8. getReference Requires class-string

In ORM 3, `EntityManager::getReference()` requires a class-string type. If your code passes string variables, add `@template` annotations or cast:

```php
// OLD -- works in ORM 2
$ref = $em->getReference('App\Entity\Order', $id);

// NEW -- ORM 3 wants class-string
$ref = $em->getReference(Order::class, $id);
```

### 9. Raw SQL: Statement executeQuery No Longer Accepts Parameters

In DBAL 3, you could pass parameters inline: `$stmt->execute([$param1, $param2])`. In DBAL 4, `Statement::executeQuery()` takes **zero parameters** -- any array you pass is silently ignored. The SQL executes with literal `?` placeholders unbound, causing MySQL error 1064 (syntax error).

This is especially dangerous because **PHP does not error on extra arguments** to user-defined methods -- the parameters are silently dropped, and the bug only surfaces at runtime as a database syntax error.

```php
// OLD (DBAL 3) -- parameters passed to Statement::execute()
$stmt = $connection->prepare($sql);
$result = $stmt->execute([$param1, $param2]);       // WORKS in DBAL 3
$result = $stmt->executeQuery([$param1, $param2]);  // SILENTLY IGNORED in DBAL 4!

// NEW (DBAL 4) -- use Connection methods directly
// For SELECT:
$result = $connection->executeQuery($sql, [$param1, $param2]);

// For INSERT/UPDATE/DELETE:
$affectedRows = $connection->executeStatement($sql, [$param1, $param2]);
```

**How to find:** Grep for `->prepare(` followed by `->executeQuery(` or `->executeStatement(` where the execute call passes an array argument. If the `prepare()` result calls `executeQuery([...])` with parameters, it's broken in DBAL 4.

```bash
grep -rn "->prepare(" src/ --include="*.php"
# Then check each match -- if the prepared statement calls executeQuery/executeStatement with params, fix it
```

Also check for `executeQuery()` used on DELETE/INSERT/UPDATE statements -- DBAL 4 has separate methods:
- `Connection::executeQuery()` -- for SELECT statements (returns `Result`)
- `Connection::executeStatement()` -- for INSERT/UPDATE/DELETE (returns affected row count)

---

## JWT Middleware Replacement Pattern

If the repo uses `tuupola/slim-jwt-auth`, `tuupola/branca-middleware`, or any `jimtools/*` JWT packages, **remove them** and replace with a custom PSR-15 middleware using `firebase/php-jwt` directly.

**Remove from composer.json:**
```json
"tuupola/slim-jwt-auth": "*",
"jimtools/jwt-auth": "*"
```

**Replace with** a proper PSR-15 middleware class:
```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class JwtMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader || !preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            return $this->jsonResponse(401, ['message' => 'Token not provided']);
        }

        try {
            $decoded = JWT::decode($matches[1], new Key(getenv('JWT_SECRET'), 'HS256'));
            $request = $request->withAttribute('token', (array) $decoded);
        } catch (\Exception $e) {
            return $this->jsonResponse(401, ['message' => $e->getMessage()]);
        }

        return $handler->handle($request);
    }

    private function jsonResponse(int $status, array $data): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

**Why:** `tuupola/slim-jwt-auth` is tightly coupled to Slim 3, `jimtools/jwt-auth` is unmaintained. Firebase JWT is the canonical PHP JWT library with active maintenance.

---

## Database Charset Migration Pattern

### Create Migration

```php
final class VersionYYYYMMDDHHMMSS extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Standardize charset to utf8mb4 for MySQL 8.4 compatibility';
    }

    public function up(Schema $schema): void
    {
        // ALTER each table individually
        $this->addSql('ALTER TABLE {table} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE {table} CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
    }
}
```

### Update Base SQL

Change all `DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci` to `utf8mb4 COLLATE utf8mb4_unicode_ci`.

### Update Connection Config

```php
'charset' => 'utf8mb4',  // was 'utf8'
```

### Index Length Warning

`utf8mb4` uses 4 bytes per character vs 3 for `utf8`. A `VARCHAR(255)` column with a single-column index uses 1020 bytes with `utf8mb4`. On older `ROW_FORMAT=COMPACT`, InnoDB has a 767-byte index prefix limit -- this will fail.

**MySQL 8.4 defaults to `ROW_FORMAT=DYNAMIC`** (3072-byte limit), so this is usually fine. But verify:

```sql
-- Check current row format for each table
SELECT TABLE_NAME, ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE();
```

If any tables use `COMPACT`, either:
- Add `ROW_FORMAT=DYNAMIC` to the charset migration
- Or reduce indexed `VARCHAR(255)` columns to `VARCHAR(191)` (191 * 4 = 764 bytes, under the 767 limit)

### .env and Environment Variables

Check if `.env` or `.env.example` files reference database connection details that need updating:
- Database driver/host changes (if switching from MariaDB)
- Any hardcoded charset references
- Redis connection changes (if Redis version upgrade changes defaults)
