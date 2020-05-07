import template from './sw-sales-channel-google-introduction.html.twig';
import './sw-sales-channel-google-introduction.scss';

const { Component, State, Service, Mixin, Utils } = Shopware;
const { mapState } = Component.getComponentHelper();

Component.register('sw-sales-channel-google-introduction', {
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

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
        },

        async mountedComponent() {
            try {
                const config = await Service('systemConfigApiService').getValues('core.googleShopping');

                const gauthOption = {
                    clientId: config['core.googleShopping.clientId'],
                    scope: 'profile email https://www.googleapis.com/auth/content',
                    prompt: 'consent'
                };

                await Service('googleAuthService').load(gauthOption);
            } catch (error) {
                this.showErrorNotification(error);
            }
        },

        updateButtons() {
            const buttonRight = {
                label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonNext'),
                variant: 'primary',
                action: 'sw.sales.channel.detail.base.step-2',
                disabled: false,
                isLoading: false
            };

            const buttonProcessRight = {
                label: this.$tc('sw-sales-channel.modalGooglePrograms.step-1.buttonConnect'),
                variant: 'primary',
                action: this.onClickConnect,
                disabled: this.isLoading || this.isProcessSuccessful,
                isLoading: this.isLoading,
                isProcessSuccessful: this.isProcessSuccessful,
                processFinish: this.processFinish
            };

            const buttonConfig = {
                right: this.googleShoppingAccount && !this.isProcessSuccessful
                    ? buttonRight
                    : buttonProcessRight,
                left: {
                    label: this.$tc('global.default.cancel'),
                    variant: null,
                    action: this.onCloseModal,
                    disabled: this.isLoading || this.isProcessSuccessful
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        async onClickConnect() {
            this.isLoading = true;
            this.isProcessSuccessful = false;

            try {
                const authCode = await Service('googleAuthService').getAuthCode();
                const { data: googleShoppingAccount } = await Service('googleShoppingService').connectGoogle(this.salesChannel.id, authCode);

                const googleShoppingAccountData = Utils.get(googleShoppingAccount, 'data', null);

                if (googleShoppingAccountData) {
                    const newGoogleShoppingAccount = {
                        ...this.googleShoppingAccount,
                        ...Utils.object.pick(googleShoppingAccountData, ['name', 'email', 'picture'])
                    };

                    State.commit('swSalesChannel/setGoogleShoppingAccount', newGoogleShoppingAccount);

                    this.isProcessSuccessful = true;
                }
            } catch (error) {
                this.showErrorNotification(error);
            } finally {
                this.isLoading = false;
            }
        },

        processFinish() {
            this.isProcessSuccessful = false;
            this.$router.push({ name: 'sw.sales.channel.detail.base.step-2' });
        },

        showErrorNotification(error) {
            this.createNotificationError({
                title: this.$tc('sw-sales-channel.modalGooglePrograms.titleError'),
                message: this.getErrorMessage(error)
            });
        },

        getErrorMessage(error) {
            // Show error message based on https://developers.google.com/identity/sign-in/web/reference#googleauthsigninoptions
            const { error: errorCode, details } = error;
            if (errorCode && details) {
                return this.$t('sw-sales-channel.modalGooglePrograms.step-1.messageErrorGoogleAuth', { error: `[${error.error}] - ${error.details}` });
            }

            if (errorCode) {
                return this.$t('sw-sales-channel.modalGooglePrograms.step-1.messageErrorGoogleAuth', { error: `[${error.error}]` });
            }

            if (Utils.get(error, 'response.data.errors[0].detail')) {
                return error.response.data.errors[0].detail;
            }

            return this.$tc('sw-sales-channel.modalGooglePrograms.step-1.messageErrorDefault');
        }
    }
});
