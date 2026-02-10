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
 *
 * @private
 */
export interface ExtensionContext {
    /**
     * Identifier for the extension (full extension URL when known, otherwise origin).
     */
    id: string;
}

/**
 * Registry of extension origin -> current iframe href.
 * Populated by sw-iframe-renderer so extension-api can resolve the correct href
 * for cross-origin iframes (where source.location.href is not accessible).
 *
 * @private
 */
const extensionHrefByOrigin = ref<Record<string, string>>({});

const extensionContextStore = Shopware.Store.register('extensionContext', () => {
    const currentExtensionContext = ref<ExtensionContext | null>(null);

    const _setCurrentExtensionContext = (context: ExtensionContext | null) => {
        currentExtensionContext.value = context;
    };

    const wrapWithExtensionContext = <T>(context: ExtensionContext, callback: () => T): T => {
        const before = currentExtensionContext.value;
        currentExtensionContext.value = context;
        try {
            return callback();
        } finally {
            currentExtensionContext.value = before;
        }
    };

    /**
     * Register the current iframe URL for an origin. Used by sw-iframe-renderer
     * so that the extension context id can be the full href when handling messages.
     *
     * @private
     */
    const registerExtensionHref = (origin: string, href: string) => {
        extensionHrefByOrigin.value = { ...extensionHrefByOrigin.value, [origin]: href };
    };

    /**
     * Get the registered href for an origin, or undefined if not registered.
     *
     * @private
     */
    const getExtensionHref = (origin: string): string | undefined => {
        return extensionHrefByOrigin.value[origin];
    };

    return {
        currentExtensionContext,
        _setCurrentExtensionContext,
        wrapWithExtensionContext,
        registerExtensionHref,
        getExtensionHref,
    };
});

/**
 * Returns the current extension context (set while handling extension SDK messages) and
 * the helper to run a callback with a given extension context.
 * The context id is for now the extension URL (message origin).
 * Uses the extensionContext Pinia store for shared state.
 *
 * @private
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
