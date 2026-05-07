import template from './sw-landing-page-view.html.twig';

const { Mixin } = Shopware;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
            default: false,
        },
    },

    computed: {
        landingPage() {
            return Shopware.Store.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Shopware.Store.get('cmsPage').currentPage;
        },

        useMeteorTabs() {
            return Shopware.Feature.isActive('V6_8_0_0');
        },

        activeTab() {
            const itemNames = this.tabItems.map((tabItem) => tabItem.name);

            if (itemNames.includes(this.$route.name)) {
                return this.$route.name;
            }

            return itemNames[0] ?? 'sw.category.landingPageDetail.base';
        },

        tabItems() {
            return [
                this.createTabItem('sw-landing-page.view.general', { name: 'sw.category.landingPageDetail.base' }),
                this.createTabItem(
                    'sw-landing-page.view.cms',
                    { name: 'sw.category.landingPageDetail.cms' },
                    {
                        disabled: !this.acl.can('landing_page.editor'),
                    },
                ),
            ];
        },
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
