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

    inject: [
        'acl',
        'feature',
    ],

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

        categoryViewTabs() {
            const createRouteTab = (label, routeName, additionalProperties = {}) => {
                return {
                    label: this.$t(label),
                    name: routeName,
                    onClick: () => {
                        void this.$router.push({ name: routeName });
                    },
                    ...additionalProperties,
                };
            };

            const tabs = [
                createRouteTab('sw-category.view.general', 'sw.category.detail.base', {
                    hasError: this.swCategoryViewError,
                }),
            ];

            if (this.isPage && !this.isCustomEntity) {
                tabs.push(createRouteTab('sw-category.view.products', 'sw.category.detail.products'));
            }

            if (this.isCustomEntity) {
                tabs.push(createRouteTab('sw-category.view.customEntity', 'sw.category.detail.customEntity'));
            }

            if (this.cmsPage || this.isPage) {
                tabs.push(createRouteTab('sw-category.view.cms', 'sw.category.detail.cms'));
            }

            if (this.isPage) {
                tabs.push(createRouteTab('sw-category.view.seo', 'sw.category.detail.seo'));
            }

            return tabs;
        },

        ...mapPageErrors(errorConfig),
    },
};
