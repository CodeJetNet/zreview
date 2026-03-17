# Testing Reference — zwalden's Standards

## 1. Testability Through Setters

**Rule:** Any service or security-sensitive object that's lazily initialized must have a setter so tests can inject mocks.

> "add a setter while you're at it, so it can be tested." (on HtmlPurifier)

```php
// ✅ Pattern: lazy init + setter for testability
private HTMLPurifier $htmlPurifier;

public function setHtmlPurifier(HTMLPurifier $purifier): void {
    $this->htmlPurifier = $purifier;
}

private function getHtmlPurifier(): HTMLPurifier {
    if (!isset($this->htmlPurifier)) {
        $this->htmlPurifier = new HTMLPurifier(HTMLPurifier_Config::createDefault());
    }
    return $this->htmlPurifier;
}
```

In tests:
```php
$controller = new AddressController(...);
$controller->setHtmlPurifier($this->createMock(HTMLPurifier::class));
```

---

## 2. Tests Must Come With Features

From PR DS-12098: "fix slim 4 issues, **with tests**" — tests were part of the PR requirement.

zwalden expects tests when:
- Adding new endpoints
- Fixing bugs (regression test)
- Adding middleware
- Changing service behavior

---

## 3. Test Structure — Indentation and Formatting

> "It's a nitpick, but why is the indentation off here? feels like it's the overall wrapping of the whole object, not the provide/useValue object..."

Tests should be consistently formatted. In Angular spec files, `TestBed.configureTestingModule` providers should be properly indented.

```typescript
// ❌ BAD — inconsistent indentation
TestBed.configureTestingModule({
providers: [
{ provide: SomeService, useValue: mockService }
]
});

// ✅ GOOD
TestBed.configureTestingModule({
    providers: [
        { provide: SomeService, useValue: mockService }
    ]
});
```

---

## 4. Avoid `console.log` in Production Code

> "no console.logs, ideally. A user would never see this, so these sort of errors must be in a message tooling which pops up for the end user."

In Angular components:
```typescript
// ❌ BAD
catch (error) {
    console.log('Failed to save award', error);
}

// ✅ GOOD — use the existing message/notification service
catch (error) {
    this.messageService.error('Failed to save award. Please try again.');
}
```

---

## 5. Integration Test Coverage for Dependency-Heavy Services

From PR DS-12214 (marketplace-client PHP 8.5 upgrade):
> "I'd feel a lot better if @stan-adr told me we had thorough integration tests for marketplace client to test pre-merge."

When doing major upgrades (PHP version bumps, composer updates), zwalden explicitly looks for integration test coverage before approving. Don't just run unit tests for framework/version migrations.

---

## 6. Test Deprecation Warnings

PR DS-12079: "fix cache type coercion bugs and **clean up test deprecations**"

Tests should be clean — no `@deprecated` usage, no suppressed warnings. Clean up deprecation notices as part of the work.
