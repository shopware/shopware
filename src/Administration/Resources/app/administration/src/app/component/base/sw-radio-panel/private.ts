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
    name: 'sw-radio-panel',
    load: () => import('./src/sw-radio-panel'),
    public: {
        props: [
            'value',
            'modelValue',
            'title',
            'description',
            'icon',
            'id',
            'name',
            'required',
            'disabled',
            'truncate',
        ],
        emits: [
            'update:modelValue',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_radio_panel',
            'sw_radio_panel_input',
            'sw_radio_panel_body',
            'sw_radio_panel_body_radio',
            'sw_radio_panel_body_slot',
            'sw_radio_panel_body_image',
            'sw_radio_panel_body_title',
            'sw_radio_panel_body_description',
        ],
        computed: [
            'checked',
        ],
        methods: [
            'toggle',
        ],
    },
});
