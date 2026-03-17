# zwalden-review

An [OpenClaw](https://openclaw.ai) / [AgentSkills](https://agentskills.io)-compatible skill that performs pre-commit code reviews against **@zwalden's** established review standards, derived from 107 real PR review comments across the [alldigitalrewards](https://github.com/alldigitalrewards) GitHub org.

## What it does

Run this skill before opening a PR to catch the patterns that consistently get flagged in code review — saving reviewer time and reducing back-and-forth.

## Install

```bash
clawhub install zwalden-review
```

Or manually — clone this repo and copy the `zwalden-review/` folder into your OpenClaw workspace's `skills/` directory.

## What it checks

| Category | Examples |
|---|---|
| **PHP type hints & access modifiers** | All properties/methods need explicit types and visibility |
| **Single-action controllers** | Use `__invoke` — one controller, one action |
| **Middleware for validation/auth** | Don't put auth/input validation logic in controllers |
| **Boolean naming** | Use `is`/`has` prefixes, not `can` or raw adjectives |
| **Security** | JWT auth on all routes, input sanitization, SQL injection prevention |
| **Architecture** | No business logic in controllers; respect layer boundaries |
| **Testing** | Tests required for new endpoints and business logic |
| **API design** | Consistent response envelopes, correct HTTP status codes |
| **Docker/CI** | Match the canonical `transaction-email` Dockerfile template |

## Usage

Once installed, trigger it in OpenClaw by saying:
- *"Review this code before I open a PR"*
- *"Would zwalden approve this?"*
- *"Check my PR"*
- *"Code review"*

Point it at a file, a diff, or paste the code directly.

## Structure

```
zwalden-review/
├── SKILL.md                    # Core skill + top-10 rules
└── references/
    ├── security.md             # Auth, injection, sanitization
    ├── php-patterns.md         # Type hints, Slim, Doctrine, AI anti-patterns
    ├── architecture.md         # Layering, controllers, middleware
    ├── testing.md              # Test requirements and patterns
    ├── api-design.md           # Response formats, status codes
    └── docker-ci.md            # Dockerfile standards, CI patterns
```

## Source

Built from 107 review comments across 100 PRs (Jan–Mar 2026). Generated and maintained with [OpenClaw](https://openclaw.ai).
