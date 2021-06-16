import template from './sw-extension-my-extensions-account.html.twig';
import './sw-extension-my-extensions-account.scss';
import extensionErrorHandler from '../../service/extension-error-handler.service';

const { State, Mixin } = Shopware;

/**
 * @private
 */
Shopware.Component.register('sw-extension-my-extensions-account', {
    template,

    inject: ['systemConfigApiService', 'shopwareExtensionService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: true,
            unsubscribeStore: null,
            form: {
                password: '',
                shopwareId: '',
            },
        };
    },

    computed: {
        isLoggedIn() {
            return State.get('shopwareExtensions').loginStatus;
        },

        shopwareId: {
            get() {
                return State.get('shopwareExtensions').shopwareId;
            },

            set(shopwareId) {
                State.commit('shopwareExtensions/storeShopwareId', shopwareId);
            },
        },
    },

    created() {
        this.createdComponent();
        this.unsubscribeStore = State.subscribe(this.showErrorNotification);
    },

    beforeDestroy() {
        this.unsubscribeStore();
    },

    methods: {
        createdComponent() {
            return this.systemConfigApiService.getValues('core.store')
                .then((response) => {
                    this.shopwareId = response['core.store.shopwareId'] || null;
                }).then(() => {
                    this.shopwareExtensionService.checkLogin();
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        logout() {
            return Shopware.Service('storeService').logout()
                .then(() => {
                    this.$emit('logout-success');
                    Shopware.State.commit('shopwareExtensions/storeShopwareId', null);
                    Shopware.State.commit('shopwareExtensions/setLoginStatus', false);
                })
                .catch((errorResponse) => {
                    const mappedErrors = extensionErrorHandler.mapErrors(errorResponse.response.data.errors);
                    Shopware.State.commit('shopwareExtensions/pluginErrorsMapped', mappedErrors);

                    throw errorResponse;
                });
        },

        login() {
            this.isLoading = true;

            return this.loginShopwareUser({
                shopwareId: this.form.shopwareId,
                password: this.form.password,
            }).then(() => {
                this.$emit('login-success');
                this.createNotificationSuccess({
                    message: this.$tc('sw-extension.my-extensions.account.loginNotificationMessage'),
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        loginShopwareUser({ shopwareId, password }) {
            return Shopware.Service('storeService').login(shopwareId, password)
                .then(() => {
                    Shopware.State.commit('shopwareExtensions/storeShopwareId', shopwareId);
                    return this.shopwareExtensionService.checkLogin();
                })
                .catch((errorResponse) => {
                    Shopware.State.commit('shopwareExtensions/storeShopwareId', null);
                    Shopware.State.commit('shopwareExtensions/setLoginStatus', false);

                    const mappedErrors = extensionErrorHandler.mapErrors(errorResponse.response.data.errors);
                    Shopware.State.commit('shopwareExtensions/pluginErrorsMapped', mappedErrors);

                    throw errorResponse;
                });
        },

        showErrorNotification({ type, payload }) {
            if (type !== 'shopwareExtensions/pluginErrorsMapped') {
                return;
            }

            payload.forEach((error) => {
                if (error.parameters) {
                    this.showApiNotification(error);
                    return;
                }
                this.createNotificationError({
                    message: this.$tc(error.message),
                });
            });
        },

        showApiNotification(error) {
            const docLink = this.$tc('sw-extension.errors.messageToTheShopwareDocumentation', 0, error.parameters);

            this.createNotificationError({
                title: error.title,
                message: `${error.message} ${docLink}`,
                autoClose: false,
            });
        },
    },
});
