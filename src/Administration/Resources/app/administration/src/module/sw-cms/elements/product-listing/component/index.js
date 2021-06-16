import template from './sw-cms-el-product-listing.html.twig';
import './sw-cms-el-product-listing.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-product-listing', {
    template,

    inject: ['feature'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            demoProductCount: 8,
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

            if (section.classList.contains('is--sidebar')) {
                this.demoProductCount = 6;
            }
        },
    },
});
