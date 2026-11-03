import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-list-flow-templates',
    load: () => import('./src/sw-flow-list-flow-templates'),
    public: {
        props: [
            'searchTerm',
        ],
        blocks: [
            'sw_flow_list_flow_templates',
        ],
        computed: [
            'flowTemplateRepository',
            'flowTemplateCriteria',
            'flowTemplateColumns',
            'assetFilter',
        ],
        methods: [
            'createComponent',
            'getList',
            'onEditFlow',
        ],
    },
});
