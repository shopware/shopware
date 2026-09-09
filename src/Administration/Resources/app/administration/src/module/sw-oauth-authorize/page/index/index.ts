import type { OAuthAuthorizationInfo, OAuthAuthorizationParams } from 'src/core/service/api/oauth-authorize.api.service';
import template from './sw-oauth-authorize-index.html.twig';
import './sw-oauth-authorize-index.scss';

const { Component } = Shopware;

type ShopwareApiError = {
    response?: {
        data?: {
            errors?: Array<{ detail?: string; title?: string }>;
        };
    };
};

const REQUIRED_PARAMS = [
    'response_type',
    'client_id',
    'redirect_uri',
    'code_challenge',
    'code_challenge_method',
] as const;

const OPTIONAL_PARAMS = [
    'state',
    'scope',
] as const;

/**
 * @sw-package framework
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'oauthAuthorizeApiService',
        'systemConfigApiService',
    ],

    data(): {
        isLoading: boolean;
        isSubmitting: boolean;
        info: OAuthAuthorizationInfo | null;
        errorMessage: string | null;
        shopName: string;
    } {
        return {
            isLoading: true,
            isSubmitting: false,
            info: null,
            errorMessage: null,
            shopName: 'Shopware',
        };
    },

    computed: {
        authorizationParams(): OAuthAuthorizationParams {
            const query = this.$route.query;
            const readString = (key: string): string | undefined => {
                const value = query[key];

                return typeof value === 'string' ? value : undefined;
            };

            const params: OAuthAuthorizationParams = {
                response_type: readString('response_type') ?? '',
                client_id: readString('client_id') ?? '',
                redirect_uri: readString('redirect_uri') ?? '',
                code_challenge: readString('code_challenge') ?? '',
                code_challenge_method: readString('code_challenge_method') ?? '',
            };

            OPTIONAL_PARAMS.forEach((key) => {
                const value = readString(key);

                if (value !== undefined) {
                    params[key] = value;
                }
            });

            return params;
        },

        hasRequiredParams(): boolean {
            return REQUIRED_PARAMS.every((key) => this.authorizationParams[key].length > 0);
        },

        username(): string {
            return Shopware.Store.get('session').currentUser?.username ?? '';
        },

        clientName(): string {
            return this.info?.client.name ?? '';
        },

        redirectHost(): string {
            if (!this.info?.redirectUri) {
                return '';
            }

            try {
                return new URL(this.info.redirectUri).host;
            } catch {
                return this.info.redirectUri;
            }
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent() {
            if (!this.hasRequiredParams) {
                this.errorMessage = this.$t('sw-oauth-authorize.error.missingParameters');
                this.isLoading = false;

                return;
            }

            await Promise.all([
                this.loadInfo(),
                this.loadShopName(),
            ]);

            this.isLoading = false;
        },

        async loadInfo() {
            try {
                this.info = await this.oauthAuthorizeApiService.getInfo(this.authorizationParams);
            } catch (error) {
                this.errorMessage = this.getErrorMessage(error);
            }
        },

        async loadShopName() {
            try {
                const values = (await this.systemConfigApiService.getValues('core.basicInformation')) as Record<
                    string,
                    unknown
                >;
                const shopName = values['core.basicInformation.shopName'];

                this.shopName = typeof shopName === 'string' && shopName.length > 0 ? shopName : 'Shopware';
            } catch {
                // Users without system config read permission still get the fallback.
                this.shopName = 'Shopware';
            }
        },

        onApprove() {
            return this.submitDecision(true);
        },

        onDeny() {
            return this.submitDecision(false);
        },

        async submitDecision(approved: boolean) {
            this.isSubmitting = true;

            try {
                const response = await this.oauthAuthorizeApiService.decide(this.authorizationParams, approved);

                this.navigateTo(response.redirectUri);
            } catch (error) {
                this.errorMessage = this.getErrorMessage(error);
                this.isSubmitting = false;
            }
        },

        getErrorMessage(error: unknown): string {
            const firstError = (error as ShopwareApiError)?.response?.data?.errors?.[0];

            return firstError?.detail ?? firstError?.title ?? this.$t('sw-oauth-authorize.error.generic');
        },

        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM). */
        navigateTo(url: string) {
            window.location.assign(url);
        },
    },
});
