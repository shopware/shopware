/**
 * @sw-package framework
 */

import type { SetupContext } from 'vue';

declare global {
    /**
     * Shopware setup compile-time macro for base components.
     *
     * Use this in `<script setup>` to declare which setup
     * bindings are public and may be replaced by component overrides. The macro
     * is removed by the Shopware setup transform and is never called at runtime.
     *
     * The same entries become the component's parent-facing surface: the
     * transform generates the `defineExpose()` call from them, so a parent
     * holding a template ref reads and writes exactly these bindings, next to
     * the component's own props. Writing `defineExpose()` by hand is rejected.
     *
     * This macro is rejected in override components. Overrides must use
     * `swDefineOverride()` to declare replacement bindings instead.
     */
    function swDefinePublic<TPublic extends Record<PropertyKey, unknown>>(bindings: TPublic): void;

    /**
     * Shopware setup compile-time macro for override components.
     *
     * Use this in `<script setup>` to declare which public base
     * component bindings are replaced by this override. The macro is removed by
     * the Shopware setup transform and is never called at runtime.
     *
     * This macro is rejected in base components. Base components must use
     * `swDefinePublic()` to expose overrideable setup bindings instead.
     */
    function swDefineOverride<TOverride extends Record<PropertyKey, unknown>>(bindings: TOverride): void;

    /**
     * Shopware setup helper for override components.
     *
     * Returns the previous public setup state passed to the generated
     * `overrideComponentSetup()` callback. This helper is injected by the
     * transform and is only valid in override components.
     */
    function useSwPreviousState<
        TPreviousState extends Record<PropertyKey, any> = Record<PropertyKey, any>,
    >(): TPreviousState;

    /**
     * Shopware setup helper for the current component props.
     *
     * Prefer Vue's `defineProps()` in new base components when possible. This
     * helper remains available for existing Shopware setup code and is replaced
     * by the transform with the generated setup props object.
     */
    function useSwProps<TProps extends Record<PropertyKey, any> = Record<PropertyKey, any>>(): TProps;

    /**
     * Shopware setup helper for the current Vue setup context.
     *
     * The helper is injected by the transform and resolves to the generated
     * setup context object.
     */
    function useSwContext<TContext = SetupContext>(): TContext;
}

export {};
