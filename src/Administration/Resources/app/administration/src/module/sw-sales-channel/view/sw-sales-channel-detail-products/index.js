import template from './sw-sales-channel-detail-products.html.twig';

const { Component } = Shopware;
const { mapGetters } = Component.getComponentHelper();

Component.register('sw-sales-channel-detail-products', {
    template,

    props: {
        salesChannel: {
            required: true,
            validator: (salesChannel) => {
                return typeof salesChannel === 'object';
            }
        },

        productExport: {
            type: Object,
            required: true
        },

        isLoading: {
            type: Boolean,
            default: false
        }
    },

    computed: {
        ...mapGetters('swSalesChannel', [
            'needToCompleteTheSetup'
        ])
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.needToCompleteTheSetup.length) {
                this.$router.push({ name: 'sw.sales.channel.detail.base' });
            }
        }
    }
});
