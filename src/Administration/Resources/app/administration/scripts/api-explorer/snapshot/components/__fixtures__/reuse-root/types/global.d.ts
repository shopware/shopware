declare const Shopware: {
    Component: {
        register(name: string, loader: () => Promise<unknown>): void;
        override(name: string, loader: () => Promise<unknown>): void;
        wrapComponentConfig: typeof import('vue').defineComponent;
    };
};

declare const FIXTURE_LAYOUT: 'compact';

declare module '*.twig' {
    const template: string;
    export default template;
}
