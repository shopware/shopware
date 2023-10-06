import template from './sw-category-entry-point-modal.html.twig';
import './sw-category-entry-point-modal.scss';

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'cmsPageTypeService',
    ],

    props: {
        salesChannelCollection: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            temporaryCollection: [],
            salesChannelOptions: [],
            selectedSalesChannelId: '',
            showLayoutSelectionModal: false,
            pageTypes: ['page', 'landingpage', 'product_list'],
            nextRoute: null,
            isDisplayingLeavePageWarning: false,
        };
    },

    computed: {
        selectedSalesChannel() {
            return this.temporaryCollection.find((channel) => channel.id === this.selectedSalesChannelId);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.salesChannelCollection.forEach((salesChannel) => {
                this.temporaryCollection.push({
                    id: salesChannel.id,
                    name: salesChannel.name,
                    homeEnabled: salesChannel.homeEnabled,
                    homeName: salesChannel.homeName,
                    homeMetaTitle: salesChannel.homeMetaTitle,
                    homeMetaDescription: salesChannel.homeMetaDescription,
                    homeKeywords: salesChannel.homeKeywords,
                    homeCmsPageId: salesChannel.homeCmsPageId,
                    homeCmsPage: salesChannel.homeCmsPage ? { ...salesChannel.homeCmsPage } : null,
                    translated: salesChannel.translated ? { ...salesChannel.translated } : null,
                });

                this.salesChannelOptions.push({
                    value: salesChannel.id,
                    label: salesChannel.translated ? salesChannel.translated.name : salesChannel.name,
                });
            });

            if (this.salesChannelCollection.length > 0) {
                this.selectedSalesChannelId = this.salesChannelOptions[0].value;
            }
        },

        closeModal() {
            this.$emit('modal-close');
        },

        getCmsPageTypeName(name) {
            const fallback = this.$tc('sw-category.base.cms.defaultDesc');

            if (!name) {
                return fallback;
            }

            const nameSnippetKey = this.cmsPageTypeService.getType(name)?.title;
            return nameSnippetKey ? this.$tc(nameSnippetKey) : fallback;
        },

        onLayoutSelect(layoutId, layout) {
            this.selectedSalesChannel.homeCmsPage = layout;
            this.selectedSalesChannel.homeCmsPageId = layoutId;
        },

        onLayoutReset() {
            this.onLayoutSelect(null, null);
        },

        openInPagebuilder() {
            let to = { name: 'sw.cms.create' };
            if (this.selectedSalesChannel.homeCmsPage) {
                to = { name: 'sw.cms.detail', params: { id: this.selectedSalesChannel.homeCmsPageId } };
            }

            if (this.hasNotAppliedChanges()) {
                this.isDisplayingLeavePageWarning = true;
                this.nextRoute = to;
                return;
            }

            this.closeModal();

            this.$nextTick(() => {
                this.$router.push(to);
            });
        },

        openLayoutModal() {
            if (!this.acl.can('category.editor')) {
                return;
            }

            this.showLayoutSelectionModal = true;
        },

        closeLayoutModal() {
            this.showLayoutSelectionModal = false;
        },

        applyChanges() {
            for (let i = 0; i < this.temporaryCollection.length; i += 1) {
                const tempSalesChannel = this.temporaryCollection[i];
                const realSalesChannel = this.salesChannelCollection[i];

                realSalesChannel.name = tempSalesChannel.name;
                realSalesChannel.homeEnabled = tempSalesChannel.homeEnabled;
                realSalesChannel.homeName = tempSalesChannel.homeName;
                realSalesChannel.homeMetaTitle = tempSalesChannel.homeMetaTitle;
                realSalesChannel.homeMetaDescription = tempSalesChannel.homeMetaDescription;
                realSalesChannel.homeKeywords = tempSalesChannel.homeKeywords;
                realSalesChannel.homeCmsPageId = tempSalesChannel.homeCmsPageId;
                this.$set(realSalesChannel, 'homeCmsPage', tempSalesChannel.homeCmsPage);
            }

            this.closeModal();
        },

        hasNotAppliedChanges() {
            for (let i = 0; i < this.temporaryCollection.length; i += 1) {
                const original = this.salesChannelCollection[i];
                const copy = this.temporaryCollection[i];

                if (
                    this.isAttributeEqual(copy.name, original.name) ||
                    this.isAttributeEqual(copy.homeEnabled, original.homeEnabled) ||
                    this.isAttributeEqual(copy.homeName, original.homeName) ||
                    this.isAttributeEqual(copy.homeMetaTitle, original.homeMetaTitle) ||
                    this.isAttributeEqual(copy.homeMetaDescription, original.homeMetaDescription) ||
                    this.isAttributeEqual(copy.homeKeywords, original.homeKeywords) ||
                    this.isAttributeEqual(copy.homeCmsPageId, original.homeCmsPageId)
                ) {
                    return true;
                }
            }

            return false;
        },

        isAttributeEqual(copy, original) {
            if (copy === null) {
                copy = '';
            }

            if (original === null) {
                original = '';
            }

            return copy !== original;
        },

        onLeaveModalClose() {
            this.nextRoute = null;
            this.isDisplayingLeavePageWarning = false;
        },

        onLeaveModalConfirm(destination) {
            this.isDisplayingLeavePageWarning = false;
            this.$nextTick(() => {
                this.closeModal();
                this.$nextTick(() => {
                    this.$router.push({ name: destination.name, params: destination.params });
                });
            });
        },
    },
};
