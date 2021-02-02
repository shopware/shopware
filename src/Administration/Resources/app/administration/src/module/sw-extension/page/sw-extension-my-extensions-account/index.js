import template from './sw-extension-my-extensions-account.html.twig';
import './sw-extension-my-extensions-account.scss';

const { State, Mixin } = Shopware;

/**
 * @private
 */
Shopware.Component.register('sw-extension-my-extensions-account', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: ['systemConfigApiService'],

    data() {
        return {
            isLoading: true,
            unsubscribeStore: null,
            form: {
                password: '',
                shopwareId: ''
            }
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
                State.dispatch('shopwareExtensions/storeShopwareId', shopwareId);
            }
        }
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
                    State.dispatch('shopwareExtensions/checkLogin');
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        logout() {
            State.dispatch('shopwareExtensions/logoutShopwareUser');
        },

        login() {
            this.isLoading = true;

            return State.dispatch(
                'shopwareExtensions/loginShopwareUser', {
                    shopwareId: this.form.shopwareId,
                    password: this.form.password
                }
            ).then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-extension.my-extensions.account.loginNotificationMessage')
                });
            }).finally(() => {
                this.isLoading = false;
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
                    message: this.$tc(error.message)
                });
            });
        },

        showApiNotification(error) {
            const docLink = this.$tc('sw-extension.errors.messageToTheShopwareDocumentation', 0, error.parameters);

            this.createNotificationError({
                title: error.title,
                message: `${error.message} ${docLink}`,
                autoClose: false
            });
        }
    }
});
