import template from './sw-extension-my-extensions-account.html.twig';
import './sw-extension-my-extensions-account.scss';
import {
    type AxiosError,
    extensionErrorHandler,
    getAssetFilter,
    type MappedError,
    notificationMixin,
    shopwareExtensionService,
    storeService,
    systemConfigApiService,
    type UserInfo,
    useShopwareExtensionsStore,
    wrapComponentConfig,
} from '../dependencies';

/**
 * @sw-package checkout
 * @private
 */
export default wrapComponentConfig({
    template,

    inject: [systemConfigApiService, shopwareExtensionService, storeService],

    emits: ['logout-success', 'login-success'],

    mixins: [notificationMixin],

    data(): {
        isLoading: boolean;
        unsubscribeStore: (() => void) | null;
        form: {
            password: string;
            shopwareId: string;
        };
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
        userInfo(): UserInfo | null {
            return useShopwareExtensionsStore().userInfo;
        },

        isLoggedIn(): boolean {
            return useShopwareExtensionsStore().userInfo !== null;
        },

        assetFilter() {
            return getAssetFilter();
        },
    },

    created() {
        this.createdComponent()
            .then(() => {
                this.unsubscribeStore = useShopwareExtensionsStore().$onAction(({ name, args }) =>
                    this.showErrorNotification({ type: name, payload: args as MappedError[][] }),
                );
            })
            .catch(() => {});
    },

    beforeUnmount() {
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
                this.commitErrors(
                    errorResponse as AxiosError<{
                        errors: StoreApiException[];
                    }>,
                );
            } finally {
                await this.shopwareExtensionService.checkLogin();
            }
        },

        async login() {
            this.isLoading = true;

            try {
                await this.storeService.login(this.form.shopwareId, this.form.password);

                this.$emit('login-success');

                this.createNotificationSuccess({
                    message: this.$t('sw-extension.my-extensions.account.loginNotificationMessage'),
                });
            } catch (errorResponse) {
                this.commitErrors(
                    errorResponse as AxiosError<{
                        errors: StoreApiException[];
                    }>,
                );
            } finally {
                await this.shopwareExtensionService.checkLogin();
                this.isLoading = false;
            }
        },

        showErrorNotification({ type, payload }: { type: string; payload: MappedError[][] }) {
            if (type !== 'pluginErrorsMapped') {
                return;
            }

            payload.forEach((errors) => {
                errors.forEach((error) => {
                    if (error.parameters) {
                        this.showApiNotification(error);
                        return;
                    }

                    // Methods from mixins are not recognized
                    this.createNotificationError({
                        message: this.$t(error.message),
                    });
                });
            });
        },

        showApiNotification(error: MappedError) {
            // @ts-expect-error
            const docLink = this.$t('sw-extension.errors.messageToTheShopwareDocumentation', error.parameters, 0);

            // Methods from mixins are not recognized
            this.createNotificationError({
                title: error.title,
                message: `${error.message} ${docLink}`,
                autoClose: false,
            });
        },

        commitErrors(errorResponse: AxiosError<{ errors: StoreApiException[] }>): never {
            if (errorResponse.response) {
                const mappedErrors = extensionErrorHandler.mapErrors(errorResponse.response.data.errors);
                useShopwareExtensionsStore().pluginErrorsMapped(mappedErrors);
            }

            throw errorResponse;
        },
    },
});
