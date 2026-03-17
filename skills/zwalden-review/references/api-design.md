# API Design Reference

## 1. Route Definitions — Use Controller::class

**Rule:** Route to `Controller::class`, not string-based method references.

```php
// ❌ BAD
$app->get('/share', 'ShareController:index');
$app->post('/share/create', 'ShareController:store');

// ✅ GOOD
$app->get('/share', GetShareController::class);
$app->post('/share', CreateShareController::class);
```

---

## 2. Middleware Chaining on Routes

**Rule:** Add middleware per-route or per-group. Auth and validation middleware always present on protected routes.

```php
// ✅ Pattern
$app->group('/account', function (RouteCollectorProxy $group) {
    $group->get('/share', GetShareController::class);
    $group->post('/share', CreateShareController::class)
        ->add(PointSharingEnabledMiddleware::class);
})->add(AuthMiddleware::class);
```

---

## 3. Error Response Format

**Rule:** Return errors as JSON with consistent structure. Aggregate all errors (don't return on first failure).

```php
// ✅ Single error
return $response
    ->withStatus(400)
    ->withHeader('Content-Type', 'application/json')
    ->withBody(json_encode(['error' => 'Invalid input data: email is required']));

// ✅ Multiple errors (preferred for validation)
return $response
    ->withStatus(422)
    ->withHeader('Content-Type', 'application/json')
    ->withBody(json_encode(['errors' => [
        'email' => 'required',
        'amount' => 'must be positive integer',
    ]]));
```

Suggested pattern:
```php
$body = json_encode(['errors' => $message]);
if (is_string($message)) {
    $body = json_encode(['error' => 'Invalid input data: ' . $message]);
}
```

---

## 4. HTTP Status Codes

- `400` — Bad request (client error, malformed)
- `401` — Unauthenticated (no/invalid JWT) — handled by auth middleware
- `403` — Forbidden (authenticated but insufficient scope/permission)
- `404` — Not found
- `422` — Unprocessable entity (validation failed)
- `500` — Internal server error (logged, not exposed to client)

---

## 5. Query Parameter Handling

**Rule:** Don't wrap `$request->getQueryParams()` in a pointless function.

> "all resolveParams function does is return `$request->getQueryParams`; remove that and just call `$params = $request->getQueryParams()`"

```php
// ❌ BAD
private function resolveParams(Request $request): array {
    return $request->getQueryParams();
}

// ✅ GOOD — just call it directly
$params = $request->getQueryParams();
```

If `resolveParams` merges POST and GET, that's fine — keep it. If it only wraps one source, delete it.

---

## 6. JWT / Auth Scopes

Required scopes must be validated in middleware before the controller runs.

**Pattern:** Scope names follow `resource.action` convention (e.g., `budget.order.create`).

---

## 7. Consistent API Terminology

**Rule:** Use consistent field names across services. Inconsistency was flagged across PRs.

> "inconsistent naming here.. 'Point balance' for one, 'Shareable Points' for the other. 'Shareable Balance' is probably more appropriate here?"
> "should we define 'Issued' points vs 'Shareable' points globally? We should discuss with @jhoughtelin and Kim."

When adding new fields to responses:
- Check what existing endpoints call similar concepts
- Align with the canonical terminology (ask product/Kim if unsure)
- Be consistent between UI labels and API field names

---

## 8. Translation Locales

> "I see translation usage, but I don't see modifications to translation locales"

If a template uses `{{ 'some.key' | trans }}`, the translation key must be added to all locale files. Don't add translation usage without adding the actual translation strings.
