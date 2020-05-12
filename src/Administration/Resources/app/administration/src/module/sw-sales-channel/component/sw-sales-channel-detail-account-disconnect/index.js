import template from './sw-sales-channel-detail-account-disconnect.html.twig';
import './sw-sales-channel-detail-account-disconnect.scss';

const { Component, Service, Utils, State, Mixin } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-sales-channel-detail-account-disconnect', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        googleShoppingAccount: {
            type: Object,
            required: true
        }
    },

    computed: {
        ...mapState('swSalesChannel', [
            'merchantInfo',
            'merchantStatus'
        ]),

        ...mapGetters('swSalesChannel', [
            'googleShoppingMerchantAccount'
        ]),

        merchantAccountId() {
            return Utils.get(this.googleShoppingMerchantAccount, 'merchantId', '');
        },

        websiteUrl() {
            return Utils.get(this.merchantInfo, 'websiteUrl', '');
        },

        websiteClaimed() {
            return Utils.get(this.merchantStatus, 'websiteClaimed', false);
        },

        isSuspended() {
            return Utils.get(this.merchantStatus, 'isSuspended', false) === true;
        },

        issues() {
            return Utils.get(this.merchantStatus, 'accountLevelIssues', []);
        },

        merchantCenter() {
            return {
                id: this.merchantAccountId.length
                    ? this.merchantAccountId
                    : this.$tc('sw-sales-channel.detail.textNoAccountConnected'),

                status: this.merchantAccountId.length
                    ? this.$tc('sw-sales-channel.detail.textConnected')
                    : this.$tc('sw-sales-channel.detail.textNotConnected'),

                label: this.merchantAccountId.length
                    ? 'success'
                    : 'danger'
            };
        },

        associatedWebsite() {
            return {
                url: this.websiteUrl.length
                    ? this.websiteUrl
                    : this.$tc('sw-sales-channel.detail.textNoWebsiteClaimed'),

                status: this.websiteClaimed === true
                    ? this.$tc('sw-sales-channel.detail.textClaimed')
                    : this.$tc('sw-sales-channel.detail.textNotclaimed'),

                label: this.websiteClaimed === true
                    ? 'success'
                    : 'danger'
            };
        }
    },

    watch: {
        merchantAccountId: {
            handler() {
                if (this.merchantAccountId) {
                    this.getMerchantData();
                }
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.googleShoppingAccount && this.googleShoppingMerchantAccount) {
                this.getMerchantData();
            }
        },

        async getMerchantData() {
            State.commit('swSalesChannel/setIsLoadingMerchant', true);

            try {
                const [merchantInfo, merchantStatus] = await Promise.all([
                    this.getMerchantInfo(),
                    this.getMerchantStatus()
                ]);

                State.commit('swSalesChannel/setMerchantInfo', merchantInfo);
                State.commit('swSalesChannel/setMerchantStatus', merchantStatus);
            } catch {
                this.createNotificationError({
                    title: this.$tc('sw-sales-channel.detail.titleFetchError'),
                    message: this.$tc('sw-sales-channel.detail.messageFetchMerchantError')
                });
            } finally {
                State.commit('swSalesChannel/setIsLoadingMerchant', false);
            }
        },

        getMerchantInfo() {
            return Service('googleShoppingService').getMerchantInfo(
                this.googleShoppingAccount.salesChannelId
            );
        },

        getMerchantStatus() {
            return Service('googleShoppingService').getMerchantStatus(
                this.googleShoppingAccount.salesChannelId
            );
        },

        onDisconnectToGoogle() {
            this.$emit('on-disconnect-to-google');
        }
    }
});
