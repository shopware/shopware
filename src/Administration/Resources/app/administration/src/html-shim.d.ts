/**
 * @sw-package framework
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

declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    // eslint-disable-next-line @typescript-eslint/no-empty-object-type, @typescript-eslint/no-explicit-any
    const component: DefineComponent<{}, {}, any>;
    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export default component;
}

// The `twig` package ships without TypeScript declarations.
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
declare module 'twig' {
    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    interface TwigStatic {
        twig(options: { data: string; rethrow: boolean }): {
            tokens: Array<{
                type: string;
                value?: string;
                token?: {
                    type?: string;
                    blockName?: string;
                    output?: unknown[];
                };
            }>;
        };
    }

    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    const Twig: TwigStatic;
    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export default Twig;
}
