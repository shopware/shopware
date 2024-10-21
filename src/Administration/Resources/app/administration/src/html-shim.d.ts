/**
 * @package admin
 */

declare module '*.html.twig' {
    const content: string;

    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export default content;
}

// Only allow raw imports for html files
declare module '*.html?raw' {
    const content: string;

    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export default content;
}

// For compat build backward imports
declare module 'vue' {
    import type { CompatVue } from '@vue/runtime-dom';

    const Vue: CompatVue;
    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export default Vue;
    // eslint-disable-next-line import/no-extraneous-dependencies
    export * from '@vue/runtime-dom';
    const { configureCompat } = Vue;
    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export { configureCompat };
}

declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/ban-types
    const component: DefineComponent<{}, {}, any>;
    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export default component;
}
