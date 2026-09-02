/**
 * @sw-package framework
 */

import { registerShortcut } from 'src/core/helper/shortcut-registry.helper';

/**
 * Adapter for the Options API `shortcuts` option. It resolves each entry's method name against the
 * instance at keystroke time and registers a callback with the shared registry, so an option-based
 * and a `useShortcut()` shortcut compete for the same key on equal terms.
 *
 * @private
 */
export default {
    install(Vue) {
        const unregisterFunctions = new WeakMap();

        Vue.mixin({
            created() {
                const shortcuts = this.$options.shortcuts;

                if (!shortcuts) {
                    return;
                }

                const systemKey = () => this.$device.getSystemKey();

                unregisterFunctions.set(
                    this,
                    Object.entries(shortcuts).map(
                        ([
                            key,
                            value,
                        ]) => {
                            const functionName = typeof value === 'string' ? value : value.method;
                            const activeOption = typeof value === 'string' ? true : value.active;
                            const active = typeof activeOption === 'boolean' ? () => activeOption : activeOption.bind(this);

                            return registerShortcut({
                                key,
                                active,
                                systemKey,
                                handler: () => {
                                    if (typeof this[functionName] === 'function') {
                                        this[functionName]();
                                    }
                                },
                            });
                        },
                    ),
                );
            },

            beforeUnmount() {
                unregisterFunctions.get(this)?.forEach((unregister) => unregister());
                unregisterFunctions.delete(this);
            },
        });
    },
};
