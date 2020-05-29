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
        salesChannel: {
            type: Object,
            required: true
        }
    },

    computed: {
        ...mapState('swSalesChannel', [
            'googleShoppingAccount',
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
            if (this.googleShoppingAccount) {
                this.getAccountProfile();
            }

            if (this.googleShoppingMerchantAccount) {
                this.getMerchantData();
            }
        },

        async getAccountProfile() {
            try {
                const { data: accountProfile } = await Service('googleShoppingService').getAccountProfile(this.salesChannel.id);

                const googleShoppingAccount = { ...this.googleShoppingAccount, ...accountProfile.data };

                State.commit('swSalesChannel/setGoogleShoppingAccount', googleShoppingAccount);
            } catch {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('global.notification.notificationFetchErrorMessage')
                });
            }
        },

        async getMerchantData() {
            State.commit('swSalesChannel/setIsLoadingMerchant', true);

            try {
                const [merchantInfo, merchantStatus] = await Promise.all([
                    this.getMerchantInfo(),
                    this.getMerchantStatus()
                ]);

                const merchantInfoData = merchantInfo.data;
                const merchantStatusData = merchantStatus.data;

                State.commit('swSalesChannel/setMerchantInfo', merchantInfoData.data);
                State.commit('swSalesChannel/setMerchantStatus', merchantStatusData.data);
            } catch {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('global.notification.notificationFetchErrorMessage')
                });
            } finally {
                State.commit('swSalesChannel/setIsLoadingMerchant', false);
            }
        },

        getMerchantInfo() {
            return Service('googleShoppingService').getMerchantInfo(this.salesChannel.id);
        },

        getMerchantStatus() {
            return Service('googleShoppingService').getMerchantStatus(this.salesChannel.id);
        },

        onDisconnectToGoogle() {
            this.$emit('on-disconnect-to-google');
        }
    }
});
