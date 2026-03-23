# zreview

A Claude Code plugin with skills for code review, autonomous development, and stack upgrade guides for PHP/TypeScript teams.

## Install

```bash
# Add the marketplace
/plugin marketplace add CodeJetNet/zreview

# Install the plugin
/plugin install zreview@zreview-marketplace
```

## Skills

### zreview

Pre-commit code review against established review standards. Catches patterns that consistently get flagged in review — saving reviewer time and reducing back-and-forth.

**Trigger with:** "Review this code before I open a PR", "Would zech approve this?", "Check my PR", "Code review"

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

### adr-developer

Autonomous developer agent. Given a JIRA ticket key, handles the full lifecycle: read the ticket, clone the repo, implement with TDD, test in Docker, push, open a PR, and update JIRA.

**Trigger with:** Provide a JIRA ticket key (e.g., `DS-12397`)

### php85-fullstack-upgrade

Phased migration guide for upgrading PHP/Slim microservices to PHP 8.5 with modernized dependencies. Covers:

- Behavioral baselines (Newman/Postman)
- Docker infrastructure (PHP 8.5, MySQL 8.4, Redis 7.4, RabbitMQ 4)
- PHPUnit 9 to 12
- Slim 3 to Slim 4
- Doctrine ORM 2 to 3 / DBAL 3 to 4
- Remaining dependency upgrades
- Database charset standardization

### angular-latest-upgrade

Phased migration guide for upgrading Angular applications to the latest stable version. Covers:

- Sequential `ng update` migrations (one major at a time)
- Deprecated tooling removal (Protractor, TSLint)
- TypeScript/tsconfig modernization
- Test configuration updates (Karma/Jasmine or Jest)
- Docker/CI updates for current Node LTS
- Post-upgrade modernization paths (standalone components, signals, zoneless)

## Structure

```
zreview/
├── .claude-plugin/
│   ├── plugin.json
│   └── marketplace.json
└── skills/
    ├── zreview/
    │   ├── SKILL.md
    │   └── references/
    │       ├── security.md
    │       ├── php-patterns.md
    │       ├── architecture.md
    │       ├── testing.md
    │       ├── api-design.md
    │       └── docker-ci.md
    ├── adr-developer/
    │   └── SKILL.md
    ├── php85-fullstack-upgrade/
    │   └── SKILL.md
    └── angular-latest-upgrade/
        └── SKILL.md
```
