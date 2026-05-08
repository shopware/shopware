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
        Shopware() {
            return Shopware;
        },

        tabItems() {
            return [
                {
                    label: this.$t('sw-category.view.general'),
                    name: 'general',
                    route: { name: 'sw.category.detail.base' },
                    hasError: !!this.swCategoryViewError,
                    onClick: () => {
                        this.$router.push({ name: 'sw.category.detail.base' });
                    },
                },
                this.isPage && !this.isCustomEntity ? {
                    label: this.$t('sw-category.view.products'),
                    name: 'products',
                    route: { name: 'sw.category.detail.products' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.category.detail.products' });
                    },
                } : null,
                this.isCustomEntity ? {
                    label: this.$t('sw-category.view.customEntity'),
                    name: 'custom-entity',
                    route: { name: 'sw.category.detail.customEntity' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.category.detail.customEntity' });
                    },
                } : null,
                this.cmsPage || this.isPage ? {
                    label: this.$t('sw-category.view.cms'),
                    name: 'cms',
                    route: { name: 'sw.category.detail.cms' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.category.detail.cms' });
                    },
                } : null,
                this.isPage ? {
                    label: this.$t('sw-category.view.seo'),
                    name: 'seo',
                    route: { name: 'sw.category.detail.seo' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.category.detail.seo' });
                    },
                } : null,
            ].filter(Boolean);
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
