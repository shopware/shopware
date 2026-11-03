import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-email-field',
    load: () => import('./src/sw-email-field'),
    public: {
        props: [
            'modelValue',
            'value',
            'deprecated',
        ],
        slots: [
            'name',
            'default',
        ],
        blocks: [
            'sw_email_field',
            'sw_email_field_deprecated',
        ],
        computed: [
            'compatValue',
        ],
        methods: [
            'getSlots',
            'handleUpdateModelValue',
        ],
    },
});
