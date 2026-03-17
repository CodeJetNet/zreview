# Security Reference — zwalden's Standards

## 1. HTML Sanitization

**Rule:** Use an established library. Do not write your own sanitizer.

**Preferred:** `htmlpurifier/htmlpurifier` (PHP) — "It's very robust and mature."

```php
// ❌ BAD — DIY sanitizer (from real PR DS-12169)
class Html {
    public static function sanitize(string $input): string {
        return htmlspecialchars(strip_tags($input));
    }
}

// ✅ GOOD — use HtmlPurifier
use HTMLPurifier;
private function getHtmlPurifier(): HTMLPurifier {
    if (!isset($this->htmlPurifier)) {
        $this->htmlPurifier = new HTMLPurifier(HTMLPurifier_Config::createDefault());
    }
    return $this->htmlPurifier;
}
```

**Do the sanitization server-side, not in JavaScript:**
> "I would get rid of all the javascript and just do this on the server. Am I missing something silly?" — on client-side JS sanitization

**Inject HtmlPurifier as a Slim global** in `dependencies.php` so templates can access it:
```php
// In dependencies.php
$container->set(HTMLPurifier::class, fn() => new HTMLPurifier(...));

// In renderer/template setup — add as global
$twig->addGlobal('htmlPurifier', $container->get(HTMLPurifier::class));
```

---

## 2. Auth / JWT Protection

**Rule:** Every non-public route MUST have JWT/scope middleware. Missing auth is treated as a security bug, not a code style issue.

From actual PRs that zwalden specifically reviewed and approved:
- DS-12181: Auth check added to unprotected chat endpoints
- DS-12175: JWT auth protection added to catalog routes
- DS-12174: Scope auth added to unprotected report routes

**Pattern:**
```php
// In routes.php — group routes that need auth
$app->group('/api', function (RouteCollectorProxy $group) {
    $group->get('/reports', GetReportController::class);
})->add(JwtAuthMiddleware::class)->add(ScopeAuthMiddleware::class);
```

**Never do auth checks in the controller body:**
```php
// ❌ BAD — auth check in controller
public function __invoke(Request $request, Response $response, array $args): Response {
    if (!$this->isAuthenticated($request)) {
        return $response->withStatus(401);
    }
    // ...
}

// ✅ GOOD — controller never runs if not authenticated
// Auth handled by middleware in route definition
```

---

## 3. SQL Injection

**Rule:** Use parameterized queries exclusively. Named parameters preferred.

From PR DS-12311 (specifically named "fix sql injections"):
```php
// ❌ BAD — interpolated string in query
$sql = "SELECT * FROM users WHERE id = $id";

// ✅ GOOD — parameterized
$sql = "SELECT * FROM users WHERE id = :id";
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
```

---

## 4. Input Validation

**Rule:** Validate all input in middleware, never in controllers or services. Return aggregated errors (see architecture.md).

**Session access:** Use a session library with getter/setter, never superglobals.
```php
// ❌ BAD — superglobal
$_SESSION['email_template'] = $request->getParsedBody()['template'];

// ✅ GOOD — session library
$this->session->set('email_template', $body['template']);
```

---

## 5. Testability of Security Code

**Rule:** Security-sensitive objects (like HtmlPurifier) need setters so they can be injected in tests.

```php
// ✅ GOOD — allows test injection
private HTMLPurifier $htmlPurifier;

public function setHtmlPurifier(HTMLPurifier $purifier): void {
    $this->htmlPurifier = $purifier;
}

private function getHtmlPurifier(): HTMLPurifier {
    if (!isset($this->htmlPurifier)) {
        $this->htmlPurifier = new HTMLPurifier(...);
    }
    return $this->htmlPurifier;
}
```
