import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * A native-setup mini-module for the transform specs: its public API matches sw-example.vue.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-example',
    load: () => import('./src/sw-example.vue'),
    public: {
        props: [
            'label',
            'disabled',
            'modelValue',
        ],
        emits: [
            'save',
            'update:modelValue',
        ],
        slots: ['actions'],
        blocks: ['sw_example'],
        setup: [
            'count',
            'save',
            'value',
        ],
    },
});
