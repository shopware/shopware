/**
 * @sw-package framework
 *
 * Vite plugin that automatically injects HMR flush behavior for extensions.
 * Sends a __flushExtension message to the Administration before HMR updates,
 * allowing cleanup of registered components, tabs, menu items, etc.
 */
import type { Plugin } from 'vite';

export interface HmrFlushPluginOptions {
    /**
     * The technical name of the extension (e.g., 'TestPlugin', 'MyApp')
     */
    extensionName: string;

    /**
     * The type of extension ('plugin' or 'app')
     */
    extensionType?: 'plugin' | 'app';
}

/**
 * Creates a Vite plugin that injects HMR flush code into extensions.
 * This ensures that before any HMR update, the extension notifies the
 * Administration to clean up its previously registered state.
 *
 * @param options - Configuration options including the extension identifier
 */
export default function HmrFlushPlugin(options: HmrFlushPluginOptions): Plugin {
    const { extensionName, extensionType = 'plugin' } = options;
    const virtualModuleId = 'virtual:hmr-flush';
    const resolvedVirtualModuleId = '\0' + virtualModuleId;

    // The code that will be injected to handle HMR flush
    // Extension info is injected as string literals
    // Uses raw postMessage to avoid import resolution issues in virtual modules
    const hmrFlushCode = `
// Auto-injected HMR flush handler by Shopware
if (import.meta.hot) {
    const EXTENSION_NAME = ${JSON.stringify(extensionName)};
    const EXTENSION_TYPE = ${JSON.stringify(extensionType)};

    const sendFlushMessage = () => {
        // Use raw postMessage in the format expected by the Meteor Admin SDK
        // This avoids import resolution issues in virtual modules
        const message = JSON.stringify({
            _type: '__flushExtension',
            _data: {
                extensionName: EXTENSION_NAME,
                extensionType: EXTENSION_TYPE,
                timestamp: Date.now(),
            },
            _callbackId: '__flushExtension_' + Date.now(),
        });

        if (window.parent && window.parent !== window) {
            window.parent.postMessage(message, '*');
        }
    };

    import.meta.hot.on('vite:beforeUpdate', () => {
        sendFlushMessage();
    });

    import.meta.hot.on('vite:beforeFullReload', () => {
        sendFlushMessage();
    });
}
`;

    return {
        name: 'shopware-hmr-flush-plugin',

        resolveId(id: string) {
            if (id === virtualModuleId) {
                return resolvedVirtualModuleId;
            }
            return null;
        },

        load(id: string) {
            if (id === resolvedVirtualModuleId) {
                return hmrFlushCode;
            }
            return null;
        },

        // Transform entry files to include the HMR flush module
        transform(code: string, id: string) {
            // Only transform in development mode and for entry files
            // Check if this is likely an entry file (main.ts, main.js, index.ts, etc.)
            const isEntryFile = /[/\\](main|index)\.(ts|js|mts|mjs)$/.test(id);
            
            if (!isEntryFile) {
                return null;
            }

            // Inject import at the top of the entry file
            const injectedCode = `import '${virtualModuleId}';\n${code}`;
            
            return {
                code: injectedCode,
                map: null,
            };
        },
    };
}
