/**
 * @sw-package framework
 */

import type { SetupContext } from 'vue';

/**
 * The previous state for `useSwPreviousState<T>()`: `T` is a component name looked up in
 * `ComponentPublicApiMapping`, or the state shape itself.
 */
type SwPreviousState<T> = T extends string
    ? T extends keyof ComponentPublicApiMapping
        ? ComponentPublicApiMapping[T]
        : Record<PropertyKey, any>
    : T;

declare global {
    /**
     * Maps an extendable component name to the shape of its public setup state. Augment it to type
     * `useSwPreviousState<'<name>'>()` and the runtime override APIs.
     */
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface ComponentPublicApiMapping {}

    /**
     * Compile-time macro of a base component: declares which top-level bindings are public, so
     * overrides may replace them. The same entries become the component's exposed API for parents
     * holding a template ref, next to its props, so writing `defineExpose()` is rejected.
     *
     * Call it once, as a statement, with shorthand entries: `swDefinePublic({ count, save })`. The
     * transform removes the call; it is rejected in override components.
     */
    function swDefinePublic<TPublic extends Record<PropertyKey, unknown>>(bindings: TPublic): TPublic;

    /**
     * Compile-time macro of an override component: declares which public base bindings this override
     * replaces. Call it once, as a statement, with shorthand entries. The transform removes the call;
     * it is rejected in base components.
     */
    function swDefineOverride<TOverride extends Record<PropertyKey, unknown>>(bindings: TOverride): void;

    /**
     * The state of the component being overridden, as the previous overrides left it. Pass the
     * component name to type it through `ComponentPublicApiMapping`:
     * `useSwPreviousState<'sw-product-detail'>()`. Override components only.
     */
    function useSwPreviousState<
        T extends string | Record<PropertyKey, any> = Record<PropertyKey, any>,
    >(): SwPreviousState<T>;

    /**
     * The props of the component being overridden. Override components only; base components use
     * `defineProps()`.
     */
    function useSwProps<TProps extends Record<PropertyKey, any> = Record<PropertyKey, any>>(): TProps;

    /**
     * The setup context of the component being overridden. Override components only; base components
     * use Vue's `useAttrs()`, `useSlots()` or `defineEmits()`.
     */
    function useSwContext<TContext = SetupContext>(): TContext;
}

export {};
