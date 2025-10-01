Directory contains 11 Shopware 6 core development guidelines covering architecture, testing, and code quality.

**Usage**: Match your task in the matrix → Load relevant guideline files for detailed rules and patterns.

---

## Quick Reference Matrix

| Scenario | Required Guidelines |
|----------|-------------------|
| **Creating new service** | decorator-pattern, internal, final-and-internal |
| **Writing tests** | unit-tests, writing-code-for-static-analysis |
| **Database changes** | database-migations, feature-flags |
| **Adding public API** | extendability, decorator-pattern, internal |
| **Breaking changes** | feature-flags, database-migations |
| **Error handling** | domain-exceptions, writing-code-for-static-analysis |
| **PHP 8 features** | 6.5-new-php-language-features, writing-code-for-static-analysis |
| **Architecture decisions** | adr, extendability |
| **Service decoration** | decorator-pattern, extendability |
| **Type safety** | writing-code-for-static-analysis, 6.5-new-php-language-features |

---

## Guideline Registry

### Core Architecture
**extendability.md**
- **Scope**: Patterns for extension (Decoration/Factory/Visitor/Mediator/Adapter)

**decorator-pattern.md**
- **Scope**: Service decoration rules, abstract classes, getDecorated()

**adr.md**
- **Scope**: Architecture decision record template

### API Design
**internal.md**
- **Scope**: @internal annotation usage, private API marking

**final-and-internal.md**
- **Scope**: @final annotation, BC rules for classes

### Development Practices
**feature-flags.md**
- **Scope**: Feature toggles, gradual rollout, BC management

**database-migations.md**
- **Scope**: Migration rules, destructive changes, blue-green deployment

### Code Quality
**unit-tests.md**
- **Scope**: Test coverage, mocking strategy, test design principles

**writing-code-for-static-analysis.md**
- **Scope**: PHPStan compliance, type safety, static analysis patterns

**domain-exceptions.md**
- **Scope**: Exception factory pattern, error codes, HTTP status mapping

### Language Features
**6.5-new-php-language-features.md**
- **Scope**: PHP 8.1+ features (enums/readonly/promoted props/match)

