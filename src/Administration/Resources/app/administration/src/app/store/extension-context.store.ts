/**
 * @sw-package framework
 *
 * @description Store for the current extension context when handling extension SDK messages.
 * Used by extension-api to set the context while a message handler runs,
 * and by composables (e.g. use-extension-ordered-container) to read it.
 */

import { computed, ref } from 'vue';

/**
 * Context for the extension that is currently handling an SDK message.
 * For now only contains the extension id; more fields may be added later.
 */
export interface ExtensionContext {
    /**
     * Identifier for the extension. For now this is the extension URL (message origin).
     */
    id: string;
}

const extensionContextStore = Shopware.Store.register('extensionContext', () => {
    const currentExtensionContext = ref<ExtensionContext | null>(null);

    const _setCurrentExtensionContext = (context: ExtensionContext | null) => {
        currentExtensionContext.value = context;
    };

    const wrapWithExtensionContext = (context: ExtensionContext, callback: () => void) => {
        const before = currentExtensionContext.value;
        currentExtensionContext.value = context;
        try {
            return callback();
        } finally {
            currentExtensionContext.value = before;
        }
    };

    return {
        currentExtensionContext,
        _setCurrentExtensionContext,
        wrapWithExtensionContext,
    };
});

/**
 * Returns the current extension context (set while handling extension SDK messages) and
 * the helper to run a callback with a given extension context.
 * The context id is for now the extension URL (message origin).
 * Uses the extensionContext Pinia store for shared state.
 */
export function useCurrentExtensionId() {
    const store = Shopware.Store.get('extensionContext');
    const id = computed(() => store.currentExtensionContext?.id ?? null);

    return id;
}

/**
 * @private
 */
export type ExtensionContextStore = ReturnType<typeof extensionContextStore>;

/**
 * @private
 */
export default extensionContextStore;
