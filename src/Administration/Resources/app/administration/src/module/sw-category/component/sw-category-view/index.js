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

        useMeteorTabs() {
            return Shopware.Feature.isActive('V6_8_0_0');
        },

        activeTab() {
            const itemNames = this.tabItems.map((tabItem) => tabItem.name);

            if (itemNames.includes(this.$route.name)) {
                return this.$route.name;
            }

            return itemNames[0] ?? 'sw.category.detail.base';
        },

        tabItems() {
            const items = [
                this.createTabItem(
                    'sw-category.view.general',
                    { name: 'sw.category.detail.base' },
                    {
                        hasError: this.swCategoryViewError,
                    },
                ),
            ];

            if (this.isPage && !this.isCustomEntity) {
                items.push(this.createTabItem('sw-category.view.products', { name: 'sw.category.detail.products' }));
            }

            if (this.isCustomEntity) {
                items.push(this.createTabItem('sw-category.view.customEntity', { name: 'sw.category.detail.customEntity' }));
            }

            if (this.cmsPage || this.isPage) {
                items.push(this.createTabItem('sw-category.view.cms', { name: 'sw.category.detail.cms' }));
            }

            if (this.isPage) {
                items.push(this.createTabItem('sw-category.view.seo', { name: 'sw.category.detail.seo' }));
            }

            return items;
        },

        ...mapPageErrors(errorConfig),
    },

    methods: {
        createTabItem(label, route, additionalProperties = {}) {
            return {
                label: this.$t(label),
                name: route.name,
                onClick: () => this.$router.push(route),
                ...additionalProperties,
            };
        },
    },
};
