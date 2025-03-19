---
title: Vue 2 Options API to Vue 3 Composition API Conversion Codemod
date: 2024-10-02
area: Frontend Development
tags: [vue, migration, codemod, eslint, composition-api]
---

## Context

Our Vue.js application currently uses the Options API, which is the traditional way of writing Vue components in Vue 2. With the release of Vue 3, the Composition API was introduced, offering improved code organization, better TypeScript support, and enhanced reusability of component logic. For more detailed information about the reasons for migrating to the Composition API, see the [documentation entry](https://developer.shopware.com/docs/guides/plugins/plugins/administration/system-updates/vue-native.html).

To modernize our codebase and take advantage of these benefits, we need to migrate our existing Vue 2 Options API components to use the Vue 3 Composition API. Manual conversion of numerous components would be time-consuming and error-prone. Therefore, we need an automated solution to assist in this migration process.

## Decision

We have decided to implement a Codemod in the form of an ESLint rule to automatically convert Vue 2 Options API components to Vue 3 Composition API. This Codemod will:

1. Identify Vue component definitions in the codebase.
2. Convert the following Options API features to their Composition API equivalents:
    - Convert `data` properties to `ref` or `reactive`.
    - Convert `computed` properties to `computed()` functions.
    - Convert `methods` to regular functions within the `setup()` function.
    - Convert lifecycle hooks to their Composition API equivalents (e.g., `mounted` to `onMounted`).
    - Convert Vue 2 specific lifecycle hooks to their Vue 3 equivalents.
    - Convert `watch` properties to `watch()` functions.
    - Handle `props` and `inject` conversions.
    - Replace `this` references with direct references to reactive variables.
    - Convert writable computed properties.
    - Handle reactive object reassignments using `Object.assign`
    - Handle correct usage of `ref` and replace the access to the value with `.value`.

3. Generate a `setup()` function containing the converted code.
4. Add necessary imports for Composition API functions (e.g., `ref`, `reactive`, `computed`, `watch`).

The Codemod will be implemented as an ESLint rule to leverage the existing ESLint ecosystem and allow for easy integration into our development workflow.

## Consequences

### Positive Consequences

1. Automated conversion will significantly reduce the time and effort required to migrate components to the Composition API.
2. Consistent conversion patterns will be applied across the codebase, ensuring uniformity.
3. The risk of human error during manual conversion is minimized.
4. Developers can gradually adopt the Composition API, as the Codemod can be run on a per-file or per-component basis.
5. The Codemod can be easily shared and used across different projects within the organization.

### Negative Consequences

1. The Codemod may not cover all edge cases or complex component structures, requiring manual intervention in some scenarios.
2. Developers will need to review and potentially refactor the converted code to ensure optimal usage of the Composition API.
3. The Codemod does not handle template changes, such as adjusting `$refs` usage.

### Limitations and Manual Steps

While the Codemod handles many aspects of the conversion, some parts will still require manual attention:


1. Template modifications: The Codemod doesn't update the component's template. Developers will need to manually adjust template bindings, event handlers, and `ref` usage.
2. Complex data structures: While simple `data` properties are converted to `ref()` or `reactive()`, more complex nested structures might require manual optimization.
3. Vuex store interactions: The Codemod doesn't automatically convert Vuex `mapState`, `mapGetters`, `mapActions`, etc. These will need to be manually converted to use the `useStore` composition function.
4. Mixins: The Codemod doesn't handle the conversion of mixins. These will need to be manually refactored into composable functions.
5. Plugin usage: Certain plugins or third-party libraries that rely on the Options API might require manual updates or replacements.
6. TypeScript annotations: If the project uses TypeScript, type annotations for props, computed properties, and methods will need to be manually added or adjusted in the `setup()` function.
7. Spread operators in computed properties: The Codemod identifies these but doesn't fully convert them. A TODO comment is added for manual attention.
8. Components using render functions or JSX will need manual conversion.
9. Performance optimizations like `shallowRef` or `shallowReactive` are not automatically applied.
10. The converted code may benefit from further refactoring to extract reusable composables.
11. Error handling and edge cases in lifecycle hooks may need manual review.
12. Usage of plugins, etc. in the `this` context may need manual conversion, e.g. `$tc`, `$t`, etc.
13. Sometimes the reassignment of reactive objects over multiple lines may not be handled correctly every time.
14. Usage of `$emit` in the Options API may need manual conversion to `defineEmits` in the Composition API and then using the `emit` function.

By implementing this Codemod, we take a significant step towards modernizing our Vue.js codebase while acknowledging that some manual work will still be required to complete the migration process.
