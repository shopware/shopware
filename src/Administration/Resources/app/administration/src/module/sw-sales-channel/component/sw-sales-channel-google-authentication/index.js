import template from './sw-sales-channel-google-authentication.html.twig';

import './sw-sales-channel-google-authentication.scss';

const { Component, Mixin } = Shopware;
const { mapState } = Component.getComponentHelper();

Component.register('sw-sales-channel-google-authentication', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        salesChannel: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            isProcessSuccessful: false
        };
    },

    computed: {
        ...mapState('swSalesChannel', [
            'googleShoppingAccount'
        ])
    },

    watch: {
        isLoading: {
            handler: 'updateButtons'
        },

        isProcessSuccessful: {
            handler: 'updateButtons'
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
        },

        updateButtons() {
            const buttonConfig = {
                right: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonNext'),
                    variant: 'primary',
                    action: 'sw.sales.channel.detail.base.step-3',
                    disabled: this.isLoading || this.isProcessSuccessful
                },
                left: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonBack'),
                    variant: null,
                    action: 'sw.sales.channel.detail.base.step-1',
                    disabled: this.isLoading || this.isProcessSuccessful
                }
            };

            this.$emit('buttons-update', buttonConfig);
        }
    }
});
