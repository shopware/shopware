/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

declare module '*.html.twig' {
    const content: string;

    export default content;
}

// Only allow raw imports for html files
declare module '*.html?raw' {
    const content: string;

    export default content;
}

declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    // eslint-disable-next-line @typescript-eslint/no-empty-object-type, @typescript-eslint/no-explicit-any
    const component: DefineComponent<{}, {}, any>;
    export default component;
}

// The `twig` package ships without TypeScript declarations.
declare module 'twig' {
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

    const Twig: TwigStatic;
    export default Twig;
}
