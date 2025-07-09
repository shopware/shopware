import template from './sw-cms-el-cross-selling.html.twig';
import './sw-cms-el-cross-selling.scss';

const { Mixin } = Shopware;
const { isEmpty } = Shopware.Utils.types;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('placeholder'),
    ],

    data() {
        return {
            sliderBoxLimit: 3,
            resizeObserver: null,
        };
    },

    computed: {
        demoProductElement() {
            return {
                config: {
                    boxLayout: {
                        source: 'static',
                        value: this.element.config.boxLayout.value,
                    },
                    displayMode: {
                        source: 'static',
                        value: this.element.config.displayMode.value,
                    },
                    elMinWidth: {
                        source: 'static',
                        value: this.element.config.elMinWidth.value,
                    },
                },
            };
        },

        sliderBoxMinWidth() {
            if (this.element.config.elMinWidth.value && this.element.config.elMinWidth.value.indexOf('px') > -1) {
                return `repeat(auto-fit, minmax(${this.element.config.elMinWidth.value}, 1fr))`;
            }

            return null;
        },

        currentDeviceView() {
            return this.cmsPageState.currentCmsDeviceView;
        },

        crossSelling() {
            if (!this.element.data.product || !this.element.data.product.crossSellings.length) {
                return {
                    name: 'Cross selling title',
                };
            }

            return this.element.data.product.crossSellings[0];
        },

        crossSellingProducts() {
            return this.element.data.product.crossSellings.length
                ? this.element.data.product.crossSellings[0].assignedProducts
                : [];
        },

        currentDemoEntity() {
            if (this.cmsPageState.currentMappingEntity === 'product') {
                return this.cmsPageState.currentDemoEntity;
            }

            return null;
        },
    },

    watch: {
        'element.config.elMinWidth.value': {
            handler() {
                this.setSliderRowLimit();
            },
        },

        currentDeviceView() {
            setTimeout(() => {
                this.setSliderRowLimit();
            }, 400);
        },

        '$refs.productHolder': {
            handler(newVal) {
                if (newVal && this.resizeObserver) {
                    // Re-attach observer when ref becomes available
                    this.resizeObserver.observe(newVal);
                }
            },
            immediate: true,
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUnmount() {
        this.beforeUnmountComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('cross-selling');
            this.initElementData('cross-selling');
        },

        mountedComponent() {
            this.setSliderRowLimit();
            this.initResizeObserver();
        },

        beforeUnmountComponent() {
            this.destroyResizeObserver();
        },

        initResizeObserver() {
            this.destroyResizeObserver();

            this.resizeObserver = new ResizeObserver(() => {
                window.requestAnimationFrame(() => {
                    this.setSliderRowLimit();
                });
            });

            if (this.$refs.productHolder) {
                this.resizeObserver.observe(this.$refs.productHolder);
            }

            this.$nextTick(() => {
                if (this.$refs.productHolder && this.resizeObserver) {
                    this.resizeObserver.observe(this.$refs.productHolder);
                }
            });
        },

        destroyResizeObserver() {
            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
                this.resizeObserver = null;
            }
        },

        setSliderRowLimit() {
            if (isEmpty(this.element.config)) {
                this.createdComponent();
            }

            const productHolder = this.$refs.productHolder;
            if (this.currentDeviceView === 'mobile' || productHolder?.offsetWidth < 500) {
                this.sliderBoxLimit = 1;
                return;
            }

            if (
                !this.element.config.elMinWidth.value ||
                this.element.config.elMinWidth.value === 'px' ||
                this.element.config.elMinWidth.value.indexOf('px') === -1
            ) {
                this.sliderBoxLimit = 3;
                return;
            }

            if (parseInt(this.element.config.elMinWidth.value.replace('px', ''), 10) <= 0) {
                return;
            }

            if (!productHolder) {
                return;
            }

            // Subtract to fake look in storefront which has more width
            const fakeLookWidth = 100;
            const boxWidth = productHolder.offsetWidth;
            const elGap = 32;
            let elWidth = parseInt(this.element.config.elMinWidth.value.replace('px', ''), 10);

            if (elWidth >= 300) {
                elWidth -= fakeLookWidth;
            }

            this.sliderBoxLimit = Math.floor(boxWidth / (elWidth + elGap));
        },

        getProductEl(product) {
            return {
                config: {
                    boxLayout: {
                        source: 'static',
                        value: this.element.config.boxLayout.value,
                    },
                    displayMode: {
                        source: 'static',
                        value: this.element.config.displayMode.value,
                    },
                },
                data: {
                    product,
                },
            };
        },
    },
};
