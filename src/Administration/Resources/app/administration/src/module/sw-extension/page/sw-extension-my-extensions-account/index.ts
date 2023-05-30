import type { AxiosError } from 'axios';
import template from './sw-extension-my-extensions-account.html.twig';
import './sw-extension-my-extensions-account.scss';
import extensionErrorHandler from '../../service/extension-error-handler.service';
import type { MappedError } from '../../service/extension-error-handler.service';
import type { UserInfo } from '../../../../core/service/api/store.api.service';

const { State, Mixin } = Shopware;

/**
 * @package merchant-services
 * @private
 */
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
        userInfo(): UserInfo|null {
            return State.get('shopwareExtensions').userInfo;
        },

        isLoggedIn(): boolean {
            return State.get('shopwareExtensions').userInfo !== null;
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
            this.isLoading = true;

            try {
                await this.storeService.login(this.form.shopwareId, this.form.password);

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
