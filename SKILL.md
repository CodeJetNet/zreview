---
name: zwalden-review
description: "Pre-commit code review against zwalden's standards. Use when: (1) asked to review code before a PR, (2) 'would zwalden approve', (3) 'code review', (4) 'check my PR', (5) 'pre-commit review'. Reviews PHP/TypeScript code for the alldigitalrewards org against zwalden's actual review patterns."
---

# zwalden-review

## How to Use This Skill

1. Read the diff or files being reviewed
2. Work through the **Top-10 Checklist** below — flag any violations
3. Consult `references/` for detailed rules in each category
4. Report findings in two sections: **🚩 Request Changes** (must-fix) and **💬 Nitpicks** (optional improvements)

---

## Top-10 Rules zwalden Consistently Enforces

These are ordered by frequency in his actual reviews. Flag all of them.

### 1. No `mixed` Types — Use Explicit Types
Never use `mixed` for properties, parameters, or return types. If it can be null, use `?int`, `?string`, etc. PHP 8+ has the tools; use them.
```php
// ❌ BAD
private mixed $userId;
// ✅ GOOD
private ?int $userId;
```

### 2. Always Declare Access Modifiers
Every class member and method must have `public`, `protected`, or `private`. No exceptions. This applies to TypeScript too (`public`, `private`, `protected` on class properties).
```php
// ❌ BAD
$htmlPurifier;
function doThing() {}
// ✅ GOOD
private HtmlPurifier $htmlPurifier;
private function doThing(): void {}
```

### 3. Validation Belongs in Middleware — Not Controllers
Controllers should be thin happy-path handlers. Auth checks, input validation, feature flag gates — all of it belongs in middleware. If invalid state could "make it" to the controller, that's a bug.
```php
// ❌ BAD — validation in controller __invoke
if (!$this->authService->isLoggedIn()) { return $response->withStatus(401); }
// ✅ GOOD — middleware handles this before controller is ever called
```

### 4. One Controller Per Route Action with `__invoke`
Controllers should handle one action. Route using `Controller::class` (not string method references). Use `__invoke` as the single entry point.
```php
// ❌ BAD
$app->get('/share', 'ShareController:index');
$app->post('/share', 'ShareController:store');
// ✅ GOOD
$app->get('/share', GetShareController::class);
$app->post('/share', CreateShareController::class);
```

### 5. Return Early — Eliminate Else After Return
When a condition returns/throws, drop the else. This reduces nesting and cognitive load.
```php
// ❌ BAD
if ($isTriggerYearly) {
    $control->setValue(1);
} else {
    $control->enable();
}
// ✅ GOOD
if ($isTriggerYearly) {
    $control->setValue(1);
    return;
}
$control->enable();
```

### 6. Boolean Naming: `is` or `has` — Never `can`
Booleans and boolean-returning methods must be prefixed with `is` or `has`. "cans are an anatomical thing."
```php
// ❌ BAD
canResendEmail, canSharePoints
// ✅ GOOD
isResendable, hasShareablePoints, isPointSharingEnabled
```

### 7. No Redundant Docblocks
If PHP/TypeScript code already declares types, don't repeat them in `@param` or `@return` docblocks. Only use docblocks for info the type system can't express (`@throws`, complex descriptions).
```php
// ❌ BAD
/** @return int */
public function getCount(): int {}
// ✅ GOOD
public function getCount(): int {}
```

### 8. Don't Conflate Entities and DTOs
- **DTO**: Moves data between layers (request → service → response)
- **Entity**: Represents a domain object with identity and lifecycle
- Never use generic `object` as a return type — create a proper DTO

### 9. Aggregate Validation Errors — Don't Fail on First
Middleware/validators should collect ALL errors and return them together. Failing on first error makes debugging painful for the caller.
```php
// ❌ BAD — stops at first error
if (!$name) return errorResponse('name required');
if (!$email) return errorResponse('email required');
// ✅ GOOD
$errors = [];
if (!$name) $errors['name'] = 'required';
if (!$email) $errors['email'] = 'required';
if ($errors) return errorResponse($errors);
```

### 10. Import Classes with `use` — No Inline FQCN Backslashes
```php
// ❌ BAD
$dt = new \DateTime();
// ✅ GOOD
use DateTime;
$dt = new DateTime();
```

---

## Reference Files

For detailed rules with more examples:

- [`references/security.md`](references/security.md) — Auth, sanitization, injection prevention
- [`references/php-patterns.md`](references/php-patterns.md) — Types, access modifiers, PHP 8 idioms, imports, DTOs
- [`references/architecture.md`](references/architecture.md) — Controllers, middleware, SRP, layering
- [`references/testing.md`](references/testing.md) — Testability, setters for DI, test structure
- [`references/api-design.md`](references/api-design.md) — Response formats, error payloads, route patterns
- [`references/docker-ci.md`](references/docker-ci.md) — Canonical Dockerfile pattern, CI/CD requirements

---

## LGTM vs Request Changes

**LGTM (zwalden approves when):**
- Controllers use `__invoke`, one per route action
- All types explicitly declared throughout (no `mixed`)
- Access modifiers on every member
- Validation logic in middleware, not controllers
- Early returns, no else-after-return
- Boolean names start with `is` or `has`
- Docblocks only where they add value beyond types

**Request Changes (zwalden blocks when):**
- `mixed` type used where a real type is knowable
- Validation logic in controller body
- Multi-action controllers or string-based route handlers
- Missing access modifiers
- `can` prefix on booleans
- DIY sanitization instead of established libraries
- Generic `object` return type instead of a proper DTO
- Classes not imported; FQCN used inline with `\`

**Nitpick (zwalden flags but doesn't block on):**
- Redundant docblocks
- First-error-only vs aggregated error responses
- Overly long methods that could be split
- Inline JavaScript that could be conditional template includes
- Console.log statements left in TypeScript
- Missing timezone context in date displays
