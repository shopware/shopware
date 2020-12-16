import template from './sw-product-detail-layout.html.twig';

const { Component, State, Context } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-product-detail-layout', {
    template,

    inject: ['repositoryFactory', 'feature'],

    data() {
        return {
            showLayoutModal: false
        };
    },

    computed: {
        cmsPageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        ...mapState('swProductDetail', [
            'product'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading'
        ]),

        ...mapState('cmsPageState', [
            'currentPage'
        ])
    },

    watch: {
        product({ cmsPageId }) {
            this.onSelectLayout(cmsPageId);
        }
    },

    methods: {
        onOpenLayoutModal() {
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
            if (this.product) {
                this.product.cmsPageId = cmsPageId;
                State.commit('swProductDetail/setProduct', this.product);
            }

            this.cmsPageRepository.get(cmsPageId, Context.api).then((cmsPage) => {
                State.commit('cmsPageState/setCurrentPage', cmsPage);
            });
        },

        onResetLayout() {
            this.onSelectLayout(null);
        }
    }
});
