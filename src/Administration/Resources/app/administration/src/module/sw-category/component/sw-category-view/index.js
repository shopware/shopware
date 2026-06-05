import template from './sw-category-view.html.twig';
import './sw-category-view.scss';
import errorConfig from '../../error-config.json';

const { mapPageErrors } = Shopware.Component.getComponentHelper();

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    mixins: [
        'placeholder',
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
            default: false,
        },
        type: {
            type: String,
            required: false,
            default: 'page',
        },
    },

    computed: {
        tabs() {
            const tabs = [
                {
                    label: this.$t('sw-category.view.general'),
                    name: 'sw.category.detail.base',
                    hasError: this.swCategoryViewError,
                    onClick: () => {
                        void this.$router.push({ name: 'sw.category.detail.base' });
                    },
                },
            ];

            if (this.isPage && !this.isCustomEntity) {
                tabs.push({
                    label: this.$t('sw-category.view.products'),
                    name: 'sw.category.detail.products',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.category.detail.products' });
                    },
                });
            }

            if (this.isCustomEntity) {
                tabs.push({
                    label: this.$t('sw-category.view.customEntity'),
                    name: 'sw.category.detail.customEntity',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.category.detail.customEntity' });
                    },
                });
            }

            if (this.cmsPage || this.isPage) {
                tabs.push({
                    label: this.$t('sw-category.view.cms'),
                    name: 'sw.category.detail.cms',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.category.detail.cms' });
                    },
                });
            }

            if (this.isPage) {
                tabs.push({
                    label: this.$t('sw-category.view.seo'),
                    name: 'sw.category.detail.seo',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.category.detail.seo' });
                    },
                });
            }

            return tabs;
        },

        category() {
            return Shopware.Store.get('swCategoryDetail').category;
        },

        isCategoryColumn() {
            return Shopware.Store.get('swCategoryDetail').isCategoryColumn;
        },

        cmsPage() {
            if (this.type === 'folder' || this.type === 'link') {
                return false;
            }

            return Shopware.Store.get('cmsPage').currentPage;
        },

        isPage() {
            return this.type !== 'folder' && this.type !== 'link';
        },

        isCustomEntity() {
            return this.type === 'custom_entity';
        },

        ...mapPageErrors(errorConfig),
    },
};
