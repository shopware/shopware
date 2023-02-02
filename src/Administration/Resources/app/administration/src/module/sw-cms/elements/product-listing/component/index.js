import template from './sw-cms-el-product-listing.html.twig';
import './sw-cms-el-product-listing.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @package content
 */
export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            demoProductCount: 8,
        };
    },

    computed: {
        currentDemoProducts() {
            return Shopware.State.get('cmsPageState').currentDemoProducts;
        },

        demoProductElement() {
            return {
                config: {
                    boxLayout: {
                        source: 'static',
                        value: this.element.config.boxLayout.value,
                    },
                    displayMode: {
                        source: 'static',
                        value: 'standard',
                    },
                },
                data: null,
            };
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-listing');
        },

        mountedComponent() {
            const section = this.$el.closest('.sw-cms-section');

            if (!this.$el?.closest?.classList?.contains) {
                return;
            }

            if (section.classList.contains('is--sidebar')) {
                this.demoProductCount = 6;
            }
        },

        getProduct(index) {
            const product = this.currentDemoProducts?.at(index - 1);

            if (product) {
                return { ...this.demoProductElement, data: { product } };
            }

            return this.demoProductElement;
        },
    },
};
