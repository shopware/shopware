import template from './sw-sales-channel-google-store-verification.html.twig';
import { getErrorMessage } from '../../helper/get-error-message.helper';

import './sw-sales-channel-google-store-verification.scss';


const { Component, Service, Utils, Mixin, State } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-sales-channel-google-store-verification', {
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
            items: [
                {
                    status: 'info',
                    key: 'siteIsVerified',
                    errorLink: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.linkVerifyWebsite'),
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textVerifiedWebsite')
                },
                {
                    status: 'info',
                    key: 'shoppingAdsPolicies',
                    errorLink: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textAdsPolicyUrl'),
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textAdsPolicy')
                },
                {
                    status: 'info',
                    key: 'contactPage',
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textAccurateContact')
                },
                {
                    status: 'info',
                    key: 'secureCheckoutProcess',
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textSecureCheckoutProcess')
                },
                {
                    status: 'info',
                    key: 'revocationPage',
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textReturnPolicy')
                },
                {
                    status: 'info',
                    key: 'shippingPaymentInfoPage',
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textBillingTerms')
                },
                {
                    status: 'info',
                    key: 'completeCheckoutProcess',
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textComplete')
                }
            ],
            isLoading: false
        };
    },

    watch: {
        isIncompleteVerification: {
            handler: 'updateButtons'
        }
    },

    computed: {
        ...mapState('swSalesChannel', [
            'storeVerification'
        ]),

        ...mapGetters('swSalesChannel', [
            'isIncompleteVerification'
        ])
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
        },

        mountedComponent() {
            if (!this.storeVerification) {
                this.verifyStore();
            } else {
                this.updateItems(this.storeVerification);
            }
        },

        updateButtons() {
            const buttonConfig = {
                right: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonNext'),
                    variant: 'primary',
                    action: 'sw.sales.channel.detail.base.step-5',
                    disabled: this.isIncompleteVerification
                },
                left: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonBack'),
                    variant: null,
                    action: 'sw.sales.channel.detail.base.step-3',
                    disabled: false
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        async verifyStore() {
            this.isLoading = true;

            try {
                const { data: storeVerification } = await Service('googleShoppingService').verifyStore(this.salesChannel.id);
                const storeVerificationData = Utils.get(storeVerification, 'data', null);

                State.commit('swSalesChannel/setStoreVerification', storeVerificationData);

                this.updateItems(storeVerificationData);
            } catch (error) {
                this.showErrorNotification(error);
            } finally {
                this.isLoading = false;
            }
        },

        getIconName(status) {
            if (status === 'success') return 'small-default-checkmark-line-medium';
            if (status === 'danger') return 'small-default-x-line-medium';

            return 'small-default-circle-medium';
        },

        showErrorNotification(error) {
            const errorDetail = getErrorMessage(error);

            this.createNotificationError({
                title: this.$tc('global.default.error'),
                message: errorDetail || this.$tc('global.notification.unspecifiedSaveErrorMessage')
            });
        },

        getFixPath(item) {
            if (item.key === 'completeCheckoutProcess' || item.key === 'secureCheckoutProcess') {
                return {
                    name: 'sw.sales.channel.detail',
                    params: { id: this.salesChannel.productExports[0].storefrontSalesChannelId }
                };
            }

            return { name: 'sw.settings.basic.information.index' };
        },

        updateItems(storeVerification) {
            this.items = this.items.map((item) => {
                return {
                    ...item,
                    status: storeVerification[item.key] ? 'success' : 'danger'
                };
            });
        }
    }
});
