import type { AxiosError } from 'axios';
import template from './sw-extension-my-extensions-account.html.twig';
import './sw-extension-my-extensions-account.scss';
import extensionErrorHandler from '../../service/extension-error-handler.service';
import type { MappedError } from '../../service/extension-error-handler.service';

const { State, Mixin } = Shopware;

/**
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'systemConfigApiService',
        'shopwareExtensionService',
        'storeService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data(): {
        isLoading: boolean,
        unsubscribeStore: (() => void)|null,
        form: {
            password: string,
            shopwareId: string,
        },
        } {
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
        userInfo() {
            return State.get('shopwareExtensions').userInfo;
        },

        isLoggedIn() {
            return State.get('shopwareExtensions').userInfo !== null;
        },

        /**
         * @deprecated tag:v6.5.0 - will be removed. Use shopwareExtensions.userInfo.email instead
         */
        shopwareId: {
            get() {
                return State.get('shopwareExtensions').userInfo?.email ?? null;
            },

            /**
             * @deprecated tag:v6.5.0 - computed shopwareId will be readonly in future versions
             */
            set() {
                Shopware.Utils.debug.warn(
                    'sw-extension-my-extensions-account',
                    'Setting the shopwareId is deprecated and has no effect',
                );
            },
        },
    },

    created() {
        this.createdComponent().then(() => {
            // component functions are always bound to this
            // eslint-disable-next-line @typescript-eslint/unbound-method
            this.unsubscribeStore = State.subscribe(this.showErrorNotification);
        })
            // eslint-disable-next-line @typescript-eslint/no-empty-function
            .catch(() => {});
    },

    beforeDestroy() {
        if (this.unsubscribeStore !== null) {
            this.unsubscribeStore();
        }
    },

    methods: {
        async createdComponent() {
            try {
                this.isLoading = true;
                await this.shopwareExtensionService.checkLogin();
            } finally {
                this.isLoading = false;
            }
        },

        async logout() {
            try {
                await this.storeService.logout();
                this.$emit('logout-success');
            } catch (errorResponse) {
                this.commitErrors(errorResponse as AxiosError<{ errors: StoreApiException[] }>);
            } finally {
                await this.shopwareExtensionService.checkLogin();
            }
        },

        async login() {
            await this.loginShopwareUser({
                shopwareId: this.form.shopwareId,
                password: this.form.password,
            });
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed. Logic will be moved to login instead
         */
        async loginShopwareUser({ shopwareId, password }: { shopwareId: string, password: string}) {
            this.isLoading = true;

            try {
                await this.storeService.login(shopwareId, password);

                this.$emit('login-success');

                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.createNotificationSuccess({
                    message: this.$tc('sw-extension.my-extensions.account.loginNotificationMessage'),
                });
            } catch (errorResponse) {
                this.commitErrors(errorResponse as AxiosError<{ errors: StoreApiException[] }>);
            } finally {
                await this.shopwareExtensionService.checkLogin();
                this.isLoading = false;
            }
        },

        showErrorNotification({ type, payload }: { type: string, payload: MappedError[]}) {
            if (type !== 'shopwareExtensions/pluginErrorsMapped') {
                return;
            }

            payload.forEach((error) => {
                if (error.parameters) {
                    this.showApiNotification(error);
                    return;
                }

                // Methods from mixins are not recognized
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.createNotificationError({
                    message: this.$tc(error.message),
                });
            });
        },

        showApiNotification(error: MappedError) {
            const docLink = this.$tc('sw-extension.errors.messageToTheShopwareDocumentation', 0, error.parameters);

            // Methods from mixins are not recognized
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.createNotificationError({
                title: error.title,
                message: `${error.message} ${docLink}`,
                autoClose: false,
            });
        },

        commitErrors(errorResponse: AxiosError<{ errors: StoreApiException[] }>): never {
            if (errorResponse.response) {
                const mappedErrors = extensionErrorHandler.mapErrors(errorResponse.response.data.errors);
                Shopware.State.commit('shopwareExtensions/pluginErrorsMapped', mappedErrors);
            }

            throw errorResponse;
        },
    },
});
