# PHP Patterns Reference

## 1. Type Hints — Non-Negotiable

**Rule:** Always declare explicit types. Never use `mixed` when you know what the type is.

### Properties
```php
// ❌ BAD
private mixed $userId;
public $program;

// ✅ GOOD
private int $userId;
private ?Program $program = null;
```

### Nullable types
Use `?Type` for nullable, not `mixed`:
```php
// ❌ BAD
private mixed $fulfillmentDate; // it's nullable DateTime

// ✅ GOOD
private ?DateTime $fulfillmentDate = null;
```

### Return types
```php
// ❌ BAD
public function getCount() { return $this->count; }

// ✅ GOOD
public function getCount(): int { return $this->count; }
```

---

## 2. Access Modifiers — Required Everywhere

**Rule:** `public`, `protected`, or `private` on every property and method. No exceptions.
> "you must define access modifiers my guy"
> "the lack of access modifiers is killing me homie"

This applies to TypeScript too — Angular components included.

```php
// ❌ BAD
$htmlPurifier;
function process() {}

// ✅ GOOD
private HTMLPurifier $htmlPurifier;
public function process(): void {}
```

**TypeScript:**
```typescript
// ❌ BAD
dateValue: string;
processAward() {}

// ✅ GOOD
public dateValue: string;
private processAward(): void {}
```

---

## 3. No Redundant Docblocks

**Rule:** If PHP/TypeScript code declares types, don't repeat them in docblocks.

```php
// ❌ BAD
/**
 * @param int $userId
 * @return User
 */
public function getUser(int $userId): User {}

// ✅ GOOD
public function getUser(int $userId): User {}

// ✅ ALSO GOOD — docblock adds value here
/**
 * @param int $userId
 * @return User
 * @throws UserNotFoundException when user does not exist
 */
public function getUser(int $userId): User {}
```

---

## 4. Import Classes — No Inline FQCN Backslashes
> "it's a pet peeve of mine, but i dislike using the prefixed \ in things like \DateTime — why not just import them proper"

```php
// ❌ BAD
public function getDate(): \DateTime {
    return new \DateTime();
}

// ✅ GOOD
use DateTime;

public function getDate(): DateTime {
    return new DateTime();
}
```

---

## 5. Memoized / Lazy-Init Properties

**Rule:** Prefer lazy initialization with `isset()` over nullable constructor properties. Don't set to `null` if the object will always be needed.

```php
// ❌ BAD (bots often generate this)
private ?HTMLPurifier $htmlPurifier = null;

public function __construct() {
    $this->htmlPurifier = null; // pointless
}

// ✅ GOOD — lazy init with isset
private HTMLPurifier $htmlPurifier;

private function getHtmlPurifier(): HTMLPurifier {
    if (!isset($this->htmlPurifier)) {
        $this->htmlPurifier = new HTMLPurifier(HTMLPurifier_Config::createDefault());
    }
    return $this->htmlPurifier;
}

// And add a setter for testability
public function setHtmlPurifier(HTMLPurifier $purifier): void {
    $this->htmlPurifier = $purifier;
}
```

---

## 6. DTOs vs Entities

**Rule:** Do not conflate. Do not use generic `object` as a return type.

- **DTO (Data Transfer Object):** Moves data between layers. Request body → service, or service → response.
- **Entity:** Domain object with identity (e.g., mapped to DB row). Has lifecycle, identity, and behavior.

```php
// ❌ BAD — generic object return
public function getFulfillmentProcess(): object { ... }

// ✅ GOOD — proper DTO
public function getFulfillmentProcess(): FulfillmentProcessDto { ... }
```

> "dont' conflate entity and dto with each other. They have different purposes. DTO is for moving data between things. Entity is a different thing altogether."

---

## 7. PHP 8 Idioms

**Rule:** Use PHP 8+ features when available. Don't leave PHP 7 patterns in PHP 8.x code.

```php
// Arrow functions (no `use` keyword needed for single expressions)
// ✅ PHP 8 style
$result = array_map(fn($entry) => $this->transform($entry), $items);

// Named arguments
$result = array_map(
    callback: fn($item) => $this->process($item),
    array: $items,
);

// Match expressions over switch
$status = match($state) {
    'active' => Status::ACTIVE,
    'inactive' => Status::INACTIVE,
    default => throw new InvalidArgumentException("Unknown state: $state"),
};
```

---

## 8. Enum Usage

**Rule:** Enums should not contain static validator methods. An enum represents a set of valid values — use it for type safety, not validation logic.

```php
// ❌ BAD — enum used as validator
enum Timezone: string {
    case UTC = 'UTC';
    
    public static function isValid(string $tz): bool { ... } // wrong
}

// ✅ GOOD — let PHP's type system handle validation via try/catch
$tz = Timezone::from($input); // throws ValueError if invalid
```

---

## 9. Boolean Method/Property Naming

**Rule:** `is` or `has` prefix — never `can`.

> "cans are an anatomical thing"

```php
// ❌ BAD
canSharePoints(), canResendEmail, canEditAward

// ✅ GOOD
isPointSharingEnabled(), hasResendableEmail, isAwardEditable
```

---

## 10. Function Naming — Verb Must Match Behavior

**Rule:** Every function name should start with a verb that accurately describes what it does and what it returns. The name is the contract.

### Verb–behavior mapping

| Verb | Behavior | Return |
|------|----------|--------|
| `is` / `has` | Tests a condition | `bool` |
| `get` | Retrieves a value; fails if missing | The value (never `null`) |
| `find` | Searches for a value; absence is normal | The value or `null` |
| `create` / `build` | Constructs a new object | The new object |
| `set` | Assigns a single value on an object | `void` (or `self` for fluent) |
| `update` | Modifies an existing record (may touch multiple fields) | `void` or the updated object |
| `delete` / `remove` | Destroys a record or detaches a relationship | `void` or `bool`/`int` (rows affected) |
| `format` / `parse` | Transforms between representations | The transformed value |
| `validate` | Checks input correctness | Error array or throws — NOT `bool` |
| `send` / `dispatch` | Pushes a message or event outward | `void` or a receipt/ID |

### Verbs to avoid

`handle`, `process`, `do`, `manage`, `execute`, `run` — these are filler words that tell the reader nothing. If you can't name the specific action, the function is probably doing too much.

### Redundant context

If the class already provides context, don't repeat it in the method name.

```php
// ❌ BAD — class is EmailService, "Email" is redundant
class EmailService {
    public function sendEmail(string $to, string $body): void {}
    public function validateEmailAddress(string $email): bool {}  // also wrong verb for bool
}

// ✅ GOOD
class EmailService {
    public function send(string $to, string $body): void {}
    public function isValidAddress(string $email): bool {}
}
```

### Verb–return type mismatches to flag

```php
// ❌ BAD — verb contradicts return type
public function getUser(int $id): ?User {}          // "get" but returns null → use "find"
public function validateEmail(string $e): bool {}   // "validate" but returns bool → use "is"
public function createReport(): void {}             // "create" but returns nothing → use "generate" or return the report
public function processPayment(Payment $p): Receipt {} // "process" is vague → use "chargePayment" or "createReceipt"

// ✅ GOOD
public function findUser(int $id): ?User {}
public function isValidEmail(string $e): bool {}
public function createReport(): Report {}
public function chargePayment(Payment $p): Receipt {}
```
