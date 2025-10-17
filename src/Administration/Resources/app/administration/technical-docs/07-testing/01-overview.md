// TODO: Add information about Playwright E2E tests later

# Testing Overview

## Introduction

The Shopware Administration uses **Jest** as the primary testing framework for unit and component tests. This document provides a basic overview of the testing infrastructure and common patterns.

For detailed examples and advanced topics, see the [official Shopware Jest testing documentation](https://developer.shopware.com/docs/guides/plugins/plugins/testing/jest-admin.html).

## Technology Stack

- **Jest** - Core testing framework
- **Vue Test Utils** - Vue.js component testing utilities
- **@vue/vue3-jest** - Vue 3 component transformer
- **TypeScript Support** - Full TypeScript support for test files

## Test File Location

Test files are co-located with the components they test:

```text
src/app/component/base/sw-button/
├── index.js
├── sw-button.html.twig
└── sw-button.spec.js          # Test file
```

**Naming Convention:** `[component-name].spec.js` or `[component-name].spec.ts`

## Running Tests

```bash
# Run all tests
composer run admin:unit

# Watch mode for development
composer run admin:unit:watch

# Generate component imports (required before first run)
npm run unit-setup
```

## Basic Test Structure

### Component Tests

```javascript
import { mount } from '@vue/test-utils';

async function createWrapper(options = {}) {
    return mount(await wrapTestComponent('sw-your-component', { sync: true }), {
        props: {
            // Component props
        },
        ...options,
    });
}

describe('src/app/component/your-component', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }
    });

    it('should render correctly', () => {
        expect(wrapper.vm).toBeTruthy();
    });
});
```

### Service Tests

```javascript
import Sanitizer from 'src/core/helper/sanitizer.helper';

describe('core/helper/sanitizer.helper.js', () => {
    it('should sanitize HTML correctly', () => {
        const result = Sanitizer.sanitize('<script>alert("xss")</script>Hello');
        expect(result).toBe('Hello');
    });
});
```

## Key Testing Utilities

- **`wrapTestComponent()`** - Resolves Shopware components with template inheritance
- **`flushPromises()`** - Ensures all async operations complete
- **`global.activeAclRoles`** - Set ACL permissions for tests
- **`global.activeFeatureFlags`** - Enable feature flags for tests

## Common Mocking Patterns

### Repository Factory

```javascript
const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/product',
    status: 200,
    response: { data: [/* mock data */] }
});
```

### Services

```javascript
beforeAll(() => {
    Shopware.Service.register('customService', () => ({
        method: jest.fn(() => Promise.resolve('result'))
    }));
});
```

### Component Stubs

```javascript 
    stubs: {
        'sw-icon': true,
        'sw-button': true,
        // Add other stubs as needed
    }
```

## Best Practices

- ✅ Test behavior, not implementation details
- ✅ Use `shallowMount` for better isolation
- ✅ Always clean up wrappers in `afterEach`
- ✅ Use `flushPromises()` after async operations
- ❌ Don't test Vue.js framework internals
- ❌ Avoid overly complex test setups

## Configuration

- **Jest Config:** `jest.config.js`
- **Test Setup:** `test/_setup/prepare_environment.js`
- **Test Pattern:** `src/**/*.spec.{js,ts}`

## Additional Resources

- [Jest Admin Testing Guide](https://developer.shopware.com/docs/guides/plugins/plugins/testing/jest-admin.html) - Comprehensive guide with detailed examples
- [Vue Test Utils Documentation](https://vue-test-utils.vuejs.org/) - Official Vue.js testing utilities
- [Jest Documentation](https://jestjs.io/) - Jest framework documentation
