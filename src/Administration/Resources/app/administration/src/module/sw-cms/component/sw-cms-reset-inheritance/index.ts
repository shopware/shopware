import template from './sw-cms-reset-inheritance.html.twig';
import './sw-cms-reset-inheritance.scss';

const { unset, cloneDeep } = Shopware.Utils.object;

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,
    props: {
        contentEntity: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            showModal: false,
        }
    },
    computed: {
        cmsPageStore() {
            return Shopware.Store.get('cmsPage');
        },
        hasOverrides() {
            return !Shopware.Utils.types.isEmpty(this.contentEntity.slotConfig);
        },
    },
    methods: {
        onConfirm() {
            this.showModal = false;

            unset(this.contentEntity, 'slotConfig');
            this.resetSlotOverrides();
        },
        /**
         * Runtime slots are the result of merging a base layout (cms_slot_translation.config)
         * with a content page (e.g. category_translation.slot_config).
         *
         * Resetting a slot means to discard content page overrides for this layout.
         */
        resetSlotOverrides() {
            this.cmsPageStore.currentPage?.sections?.forEach((section) => {
                section.blocks?.forEach((block) => {
                    block.slots?.forEach((slot) => {
                        slot.config = cloneDeep(slot.translated!.config);
                    });
                });
            });
        },
    },
})
