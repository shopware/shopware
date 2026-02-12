# Issue 19: Backward Compatibility Testing Strategy

**Phase:** Cross-Cutting (spans all phases)
**Priority:** High
**Estimate:** 2 weeks (initial setup) + ongoing
**Labels:** `migration`, `testing`, `quality-assurance`, `plugin-ecosystem`, `backward-compatibility`

---

## Summary

Design and implement a backward compatibility testing strategy that validates the migration does not break existing plugin extension patterns. This includes testing with real-world plugins, maintaining a plugin compatibility matrix, and providing a test harness that plugin developers can use to validate their own plugins.

---

## Problem

The migration changes the two systems that plugins rely on most heavily (template overrides and logic overrides). While the compatibility shims and runtime adapters (Issues #01, #02) are designed to prevent breakage, they may have edge cases. Without systematic backward compatibility testing:

- Edge cases in the shims/adapters go undetected until plugins break in production
- Plugin developers have no way to pre-validate their plugins against upcoming changes
- The core team cannot confidently assess ecosystem readiness for eventual deprecation and removal

---

## Acceptance Criteria

### Core Team Testing
- [ ] A set of "reference plugins" is maintained that exercise common extension patterns
- [ ] Reference plugins are tested automatically in CI against the current administration build
- [ ] Reference plugins cover all 6 extension scenarios from Issue #05
- [ ] Test results are published/visible to assess migration readiness
- [ ] Each component migration PR must pass reference plugin tests

### Plugin Developer Test Harness
- [ ] A test utility is published that plugin developers can use in their own test suites
- [ ] The utility validates that plugin overrides (template and logic) work correctly against the current admin version
- [ ] The utility provides clear pass/fail results with diagnostic information
- [ ] Documentation explains how to integrate the test utility

### Compatibility Matrix
- [ ] A matrix tracks which popular plugins have been tested against the migrated administration
- [ ] Matrix is updated regularly (at least per minor release during migration)
- [ ] Matrix is publicly accessible for ecosystem transparency

---

## Technical Approach

### Reference Plugin Suite

Create a set of internal test plugins that exercise common patterns:

| Plugin | Pattern Tested |
|--------|---------------|
| `test-options-api-override` | `Component.override()` with Options API config on a Composition API component |
| `test-composition-override` | `overrideComponentSetup()` on a Composition API component |
| `test-twig-template-override` | Twig `{% block %}` override on a native-block component |
| `test-native-block-override` | `<sw-block extends>` override on a native-block component |
| `test-multi-override-chain` | Multiple plugins overriding the same component |
| `test-mixin-usage` | Plugin using mixin-injected methods via override |
| `test-inject-usage` | Plugin accessing injected services via override |
| `test-complex-override` | Complex override with data, computed, methods, watch, lifecycle hooks |

### Plugin Test Harness

```javascript
// @shopware/admin-compat-test
import { testPluginOverride } from '@shopware/admin-compat-test';

describe('My Plugin Overrides', () => {
    it('sw-product-detail override works', async () => {
        const result = await testPluginOverride({
            component: 'sw-product-detail',
            override: myOverrideConfig,
            assertions: (wrapper) => {
                expect(wrapper.find('.my-custom-element').exists()).toBe(true);
                expect(wrapper.vm.myCustomMethod).toBeDefined();
            },
        });
        expect(result.passed).toBe(true);
    });
});
```

### CI Integration

```yaml
# Runs reference plugin tests on every admin-related PR
backward-compat-check:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - run: npm ci
    - run: npm run test:backward-compat
```

### Popular Plugin Testing

Coordinate with popular plugin developers or use open-source plugins to test:

1. Download/clone popular plugins
2. Install into a test instance with the migrated administration
3. Run the plugin's tests + the compat test harness
4. Record results in the compatibility matrix

---

## Testing Requirements

This issue IS the testing strategy — the deliverable is the test infrastructure itself.

- [ ] Reference plugins are created and tested
- [ ] Test harness is functional and documented
- [ ] CI integration runs on every PR
- [ ] At least 10 popular community plugins are tested and tracked

---

## Compatibility Matrix Template

| Plugin | Version | Options API Override | Composition Override | Twig Template | Native Block | Status |
|--------|---------|---------------------|---------------------|---------------|-------------|--------|
| Plugin A | 2.1.0 | Pass | N/A | Pass | N/A | Compatible |
| Plugin B | 1.5.0 | Fail (edge case) | N/A | Pass | N/A | Needs fix |
| Plugin C | 3.0.0 | N/A | Pass | N/A | Pass | Migrated |

---

## Definition of Done

- Reference plugin suite created with 8+ plugins covering all patterns
- Reference plugins tested in CI on every PR
- Plugin test harness published and documented
- Compatibility matrix established for at least 10 popular plugins
- Process documented for ongoing maintenance
