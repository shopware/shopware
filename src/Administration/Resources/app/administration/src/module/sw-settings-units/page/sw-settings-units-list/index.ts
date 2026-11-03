import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
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
