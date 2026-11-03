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
    name: 'sw-generic-custom-entity-list',
    load: () => import('./src/sw-generic-custom-entity-list'),
    public: {
        blocks: [
            'sw_generic_custom_entity_list',
        ],
        computed: [
            'customEntityName',
            'customEntityDefinition',
            'customEntityRepository',
            'adminConfig',
            'entityAccentColor',
            'columnConfig',
            'customEntityCriteria',
            'emptyStateTitle',
            'emptyStateSubline',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'getList',
            'onChangeLanguage',
            'parseSortDirection',
            'parseRoute',
            'updateRoute',
            'onSearch',
            'onColumnSort',
            'onPageChange',
            'onUpdateRecords',
        ],
    },
});
