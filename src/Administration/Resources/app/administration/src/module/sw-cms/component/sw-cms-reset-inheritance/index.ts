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
        cmsPage() {
            return this.cmsPageStore.currentPage;
        },
        hasOverrides() {
            return !Shopware.Utils.types.isEmpty(this.contentEntity.slotConfig);
        },
    },
    methods: {
        onConfirm() {
            this.showModal = false;

            this.resetSlotConfig();
            this.resetRuntimeSlotConfig();
        },
        resetSlotConfig() {
            unset(this.contentEntity, 'slotConfig');
        },
        // TODO
        resetRuntimeSlotConfig() {
            this.cmsPage.sections.forEach((section) => {
                section.blocks.forEach((block) => {
                    block.slots.forEach((slot) => {
                        slot.config = cloneDeep(slot.translated.config);
                    });
                });
            });
        },
    },
})
