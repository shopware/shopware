/**
 * @sw-package framework
 */
declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    const component: DefineComponent<object, object, unknown>;
    /** @private */
    export default component;
}
