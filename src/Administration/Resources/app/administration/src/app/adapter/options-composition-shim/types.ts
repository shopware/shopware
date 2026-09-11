/** @sw-package framework */

/** @private */
export type OverrideFn<COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string> = (
    previousState: ComponentPublicApiMapping[COMPONENT_NAME],
    props: ComponentPublicApiMapping[COMPONENT_NAME],
    context?: unknown,
) => ComponentPublicApiMapping[COMPONENT_NAME];

declare module 'vue' {
    interface ComponentCustomOptions {
        /** Maps an original Options instance member to its migrated setup binding. */
        legacyOptionsBindings?: Record<string, string>;
        /** Retains the original member categories for native Options introspection. */
        legacyOptionsMembers?: Record<string, 'data' | 'computed' | 'writable-computed' | 'method'>;
        __swExtendable?: boolean;
    }
}
