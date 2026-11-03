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
    name: 'sw-property-detail-base',
    load: () => import('./src/sw-property-detail-base'),
    public: {
        props: [
            'propertyGroup',
            'isLoading',
            'allowEdit',
        ],
        blocks: [
            'sw_property_detail_base',
            'sw_property_detail_base_name',
            'sw_property_detail_base_description',
            'sw_property_detail_filter_visible_container',
            'sw_property_detail_base_filterable',
            'sw_property_detail_base_visible_on_detail',
            'sw_property_detail_sorting_display_container',
            'sw_property_detail_display_type',
            'sw_property_detail_sorting_type',
            'sw_property_detail_position',
        ],
        computed: [
            'displayTypeOptions',
            'sortingTypeOptions',
        ],
    },
});
