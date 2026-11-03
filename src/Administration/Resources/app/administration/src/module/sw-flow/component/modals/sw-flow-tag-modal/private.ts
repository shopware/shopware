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
    name: 'sw-flow-tag-modal',
    load: () => import('./src/sw-flow-tag-modal'),
    public: {
        props: [
            'sequence',
            'action',
        ],
        emits: [
            'process-finish',
            'modal-close',
        ],
        blocks: [
            'sw_flow_tag_modal',
            'sw_flow_tag_modal_content',
            'sw_flow_tag_modal_to_field',
            'sw_flow_tag_modal_tags_field',
            'sw_flow_tag_modal_content_custom',
            'sw_flow_tag_modal_footer_cancel_button',
            'sw_flow_tag_modal_footer_save_button',
        ],
        computed: [
            'tagCriteria',
            'isNewTag',
            'tagRepository',
            'tagTitle',
        ],
        methods: [
            'createdComponent',
            'getTagCollection',
            'createTagCollection',
            'onAddTag',
            'onRemoveTag',
            'getEntityOptions',
            'getConfig',
            'fieldError',
            'onSaveTag',
            'onClose',
        ],
    },
});
