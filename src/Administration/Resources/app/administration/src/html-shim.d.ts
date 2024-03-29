/**
 * @package admin
 */

declare module '*.html.twig' {
    const content: string;

    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export default content;
}

declare module '*.html' {
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
}
