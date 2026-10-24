declare const Shopware: {
    Component: {
        register(name: string, loader: () => Promise<unknown>): void;
        extend(name: string, parent: string, loader: () => Promise<unknown>): void;
        override(name: string, config: object): void;
        wrapComponentConfig: typeof import('vue').defineComponent;
        getComponentHelper(): {
            mapPageErrors<T extends string>(config: Record<T, Record<string, string[]>>): Record<`${T}Error`, () => boolean>;
        };
    };
    Mixin: {
        getByName(name: string): object;
    };
};

declare module '*.twig' {
    const template: string;
    export default template;
}
