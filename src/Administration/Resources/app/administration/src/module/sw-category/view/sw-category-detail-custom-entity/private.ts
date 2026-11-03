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
    name: 'sw-category-detail-custom-entity',
    load: () => import('./src/sw-category-detail-custom-entity'),
    public: {
        props: [
            'isLoading',
        ],
        blocks: [
            'sw_category_detail_custom_entity',
        ],
        computed: [
            'customEntityAssignments',
            'customEntityColumns',
            'category',
            'customEntityCriteria',
            'sortingCriteria',
            'assetFilter',
        ],
        methods: [
            'onAssignmentChange',
            'onEntityChange',
            'fetchCustomEntityName',
        ],
    },
});
