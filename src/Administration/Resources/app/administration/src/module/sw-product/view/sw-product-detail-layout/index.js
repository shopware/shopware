/*
 * @package inventory
 */

import template from './sw-product-detail-layout.html.twig';
import './sw-product-detail-layout.scss';

const { Component, State, Context, Utils } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapGetters } = Component.getComponentHelper();
const { cloneDeep, merge, get } = Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'cmsService', 'feature', 'acl'],

    data() {
        return {
            showLayoutModal: false,
            isConfigLoading: false,
        };
    },

    computed: {
        cmsPageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        cmsPageId() {
            return get(this.product, 'cmsPageId', null);
        },

        showCmsForm() {
            return (!this.isLoading || !this.isConfigLoading) && !this.currentPage.locked;
        },

        ...mapState('swProductDetail', [
            'product',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
        ]),

        ...mapState('cmsPageState', [
            'currentPage',
        ]),

        cmsPageCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('previewMedia');
            criteria.addAssociation('sections');
            criteria.getAssociation('sections').addSorting(Criteria.sort('position'));

            criteria.addAssociation('sections.blocks');
            criteria.getAssociation('sections.blocks')
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('slots');

            return criteria;
        },
    },

    watch: {
        cmsPageId() {
            State.dispatch('cmsPageState/resetCmsPageState');
            this.handleGetCmsPage();
        },

        product: {
            deep: true,
            handler(value) {
                if (!value) {
                    return;
                }

                this.updateCmsPageDataMapping();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            // Keep current layout configuration if page sections exist
            const sections = this.currentPage?.sections ?? [];

            if (sections.length) {
                return;
            }

            this.handleGetCmsPage();
        },

        onOpenLayoutModal() {
            if (!this.acl.can('product.editor')) {
                return;
            }

            this.showLayoutModal = true;
        },

        onCloseLayoutModal() {
            this.showLayoutModal = false;
        },

        onOpenInPageBuilder() {
            if (!this.currentPage) {
                this.$router.push({ name: 'sw.cms.create' });
            } else {
                this.$router.push({ name: 'sw.cms.detail', params: { id: this.currentPage.id } });
            }
        },

        onSelectLayout(cmsPageId) {
            if (!this.product) {
                return;
            }

            this.product.cmsPageId = cmsPageId;
            this.product.slotConfig = null;
            State.commit('swProductDetail/setProduct', this.product);
        },

        handleGetCmsPage() {
            if (!this.cmsPageId) {
                return;
            }

            this.isConfigLoading = true;

            this.cmsPageRepository.get(this.cmsPageId, Context.api, this.cmsPageCriteria).then((cmsPage) => {
                if (this.product.slotConfig && cmsPage) {
                    cmsPage.sections.forEach((section) => {
                        section.blocks.forEach((block) => {
                            block.slots.forEach((slot) => {
                                if (!this.product.slotConfig[slot.id]) {
                                    return;
                                }

                                slot.config = slot.config || {};
                                merge(slot.config, cloneDeep(this.product.slotConfig[slot.id]));
                            });
                        });
                    });
                }

                State.commit('cmsPageState/setCurrentPage', cmsPage);
                this.updateCmsPageDataMapping();
                this.isConfigLoading = false;
            });
        },

        updateCmsPageDataMapping() {
            Shopware.State.commit('cmsPageState/setCurrentMappingEntity', 'product');
            Shopware.State.commit(
                'cmsPageState/setCurrentMappingTypes',
                this.cmsService.getEntityMappingTypes('product'),
            );
            Shopware.State.commit('cmsPageState/setCurrentDemoEntity', this.product);
        },

        onResetLayout() {
            this.onSelectLayout(null);
        },
    },
};
