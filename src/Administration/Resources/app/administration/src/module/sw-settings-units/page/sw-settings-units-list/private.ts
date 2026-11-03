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
    name: 'sw-settings-units-list',
    load: () => import('./src/sw-settings-units-list'),
    public: {
        computed: [
            'unitRepository',
            'unitList',
            'isEmpty',
            'tooltipCreate',
            'isAddingUnitsDisabled',
        ],
        methods: [
            'createdComponent',
            'createUnitsCriteria',
            'loadUnits',
            'createNewUnit',
            'saveUnit',
            'cancelUnit',
            'deleteUnit',
            'activateInlineEdit',
            'unitColumns',
            'onChangeLanguage',
            'editUnit',
        ],
    },
});
