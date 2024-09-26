import type { PluginObject } from 'vue';

/**
 * @package admin
 */
const VirtualCallStackPlugin: PluginObject<void> = {
    install(app) {
        app.mixin({
            beforeCreate() {
                // Add the virtual call stack as a non reactive value to the component instance
                // It has to be none reactive! A reactive _virtualCallStack triggers a endless Vue update cycle
                // @see async-component.factory.ts@1070
                Object.defineProperty(this, '_virtualCallStack', {
                    value: {},
                    writable: true,
                    configurable: true,
                });
            },
        });
    },
};

/* @private */
export default VirtualCallStackPlugin;
