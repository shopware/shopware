import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-settings-units-detail',
    load: () => import('./src/sw-settings-units-detail'),
    public: {
        props: [
            'unitId',
        ],
        blocks: [
            'sw_settings_units_detail',
        ],
        computed: [
            'unitRepository',
            'customFieldSetRepository',
            'customFieldSetCriteria',
        ],
        methods: [
            'loadUnit',
            'onSave',
            'onChangeLanguage',
        ],
    },
});
