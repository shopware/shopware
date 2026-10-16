/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import Feature from 'src/core/feature';

/**
 * A component for the deprecation specs that calls its own deprecated method after an `await`, where no
 * component instance is current anymore.
 *
 * @private
 */
export default defineComponent({
    template: '<div />',

    methods: {
        async load(): Promise<void> {
            await Promise.resolve();

            this.oldMethod();
        },

        oldMethod(): void {
            Feature.triggerDeprecationOrThrow('V7_0_0_0', 'oldMethod() is deprecated.');
        },
    },
});
