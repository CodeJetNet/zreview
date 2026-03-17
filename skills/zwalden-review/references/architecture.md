# Architecture Reference

## 1. Single-Action Controllers with `__invoke`

**Rule:** One controller per route action. Use `__invoke` as the single entry point. Route to `Controller::class` not string references.

> "split all of these into their own controllers. Marketplace used this whole multiple route -> controller thing from long ago, we don't need to continue that trend."

```php
// ❌ BAD — multi-method controller
class ShareController {
    public function index(Request $req, Response $res): Response { ... }
    public function store(Request $req, Response $res): Response { ... }
    public function destroy(Request $req, Response $res): Response { ... }
}

// In routes.php:
$app->get('/share', 'ShareController:index');
$app->post('/share', 'ShareController:store');

// ✅ GOOD — single-action controllers
class GetShareController {
    public function __invoke(Request $req, Response $res, array $args): Response { ... }
}
class CreateShareController {
    public function __invoke(Request $req, Response $res, array $args): Response { ... }
}

// In routes.php:
$app->get('/share', GetShareController::class);
$app->post('/share', CreateShareController::class);
```

---

## 2. Middleware for Validation, Auth, and Guards

**Rule:** Controllers should only handle the happy path. Everything that guards entry — auth, feature flags, input validation — belongs in middleware.

> "you should almost always handle controller validation in a middleware.. there's really no reason not to."
> "if you offload most of this stuff to middleware, you can tighten up your controllers to be more readable. this password check, the check invite token check, etc"

```php
// ❌ BAD — guards in controller
public function __invoke(Request $req, Response $res, array $args): Response {
    if (!$this->auth->isLoggedIn()) {
        return $res->withStatus(401)->withJson(['error' => 'unauthorized']);
    }
    if (!$this->config->isPointSharingEnabled()) {
        return $res->withStatus(403)->withJson(['error' => 'feature disabled']);
    }
    // ... actual logic
}

// ✅ GOOD — middleware handles guards
// routes.php
$app->post('/share', CreateShareController::class)
    ->add(PointSharingEnabledMiddleware::class)
    ->add(AuthenticatedMiddleware::class);

// Controller only does its job
public function __invoke(Request $req, Response $res, array $args): Response {
    // point sharing is enabled, user is authenticated — middleware guaranteed it
    $result = $this->shareService->transfer($body['amount'], $body['recipient']);
    return $res->withJson($result);
}
```

---

## 3. Single Responsibility in Methods

**Rule:** Methods should do one thing. Fat methods with multiple responsibilities should be split.

> "these methods are just doing multiple things. This could cleanly be broke into multiple methods to be more readable, testable, etc."
> "This method is doing a lot..."

**Smell:** A method that validates, fetches data, transforms it, AND sends a response.

**Fix:** Extract each concern into its own method or class.

---

## 4. Return Early — No Else After Return

**Rule:** If a code path returns (or throws), drop the else block. Reduces nesting and cognitive load.

> "return early and 'nix the else."
> "There is no value in the else { .. } here. This can be rewritten as... if you can start thinking like this, you will reduce cognitive load and increase readability."

```php
// ❌ BAD
if ($valid) {
    return $this->process();
} else {
    return $this->error();
}

// ✅ GOOD
if ($valid) {
    return $this->process();
}
return $this->error();
```

```typescript
// ❌ BAD (Angular component)
if (isTriggerYearly) {
    intervalControl?.setValue(1);
    intervalControl?.disable();
} else {
    intervalControl?.enable();
}

// ✅ GOOD
if (isTriggerYearly) {
    intervalControl?.setValue(1);
    intervalControl?.disable();
    return;
}
intervalControl?.enable();
```

---

## 5. Aggregate Validation Errors

**Rule:** Middleware/validators should collect ALL errors and return them together. Don't fail on first error.

> "a consideration would be to aggregate /all/ error messages and then return back an array so user could fix all problems, instead of piecemeal"
> "you fixed it on the validateCreateInput, but not on the validateUpdateInput, mightest well have them both aggregate errors to be consistent"

```php
// ❌ BAD — fails on first error
public function validate(array $body): void {
    if (empty($body['email'])) {
        throw new ValidationException('email required');
    }
    if (empty($body['name'])) {
        throw new ValidationException('name required');
    }
}

// ✅ GOOD — collect all errors
public function validate(array $body): array {
    $errors = [];
    if (empty($body['email'])) $errors['email'] = 'required';
    if (empty($body['name'])) $errors['name'] = 'required';
    return $errors;
}
```

---

## 6. Service vs Controller Responsibility

**Rule:** Services do work. Controllers delegate to services. Controllers don't contain business logic.

Slim controllers should look like:
1. Extract request data
2. Call service
3. Return response

If a controller is doing data transformation, validation, or business logic, that's a service concern.

---

## 7. Dependency Injection Pattern

**Rule:** Dependencies injected via constructor. For Slim controllers, the container handles DI via `Controller::class` routing.

```php
// ✅ GOOD — constructor injection
final class CreateShareController {
    public function __construct(
        private readonly ShareService $shareService,
        private readonly Logger $logger,
    ) {}

    public function __invoke(Request $req, Response $res, array $args): Response {
        $result = $this->shareService->create($req->getParsedBody());
        return $res->withJson($result);
    }
}
```

> "would rather this be __invoke and handle it's own dependency injection via checking $args in class, not in route"

---

## 8. Avoid Static Methods for Behavior

> "static usage pains me, when there's not a great reason"

Prefer instance methods. Static methods make testing harder and couple code to implementation. Valid uses: pure utility functions, factory methods when DI isn't available.
