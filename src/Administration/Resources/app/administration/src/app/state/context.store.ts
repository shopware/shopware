/**
 * @package admin
 */

import type { privileges } from '@shopware-ag/admin-extension-sdk/es/privileges/privilege-resolver';
import type { Module } from 'vuex';

type ApiAuthToken = {
    access: string,
    expiry: number,
    refresh: string
}

interface ContextState {
    app: {
        config: {
            adminWorker: null | {
                enableAdminWorker: boolean,
                enableQueueStatsWorker: boolean,
                enableNotificationWorker: boolean,
                transports: string[]
            },
            bundles: null | {
                [BundleName: string]: {
                    css: string | string[],
                    js: string | string[],
                    permissions?: privileges,
                    integrationId?: string,
                    active?: boolean,
                }
            },
            version: null | string,
            versionRevision: null | string,
        },
        environment: null | 'development' | 'production' | 'testing',
        fallbackLocale: null | string,
        features: null | {
            [FeatureKey: string]: boolean
        },
        firstRunWizard: null | boolean,
        systemCurrencyISOCode: null | string,
        systemCurrencyId: null | string,
        disableExtensions: boolean,
        /** @deprecated tag:v6.6.0 - Will be removed. Use cookie `lastActivity` instead */
        lastActivity: number,
    },
    api: {
        apiPath: null | string,
        apiResourcePath: null | string,
        assetsPath: null | string,
        authToken: null | ApiAuthToken,
        basePath: null | string,
        pathInfo: null | string,
        inheritance: null | boolean,
        installationPath: null | string,
        languageId: null | string,
        language: null | {
            name: string,
            parentId?: string,
        },
        apiVersion: null | string,
        liveVersionId: null | string,
        systemLanguageId: null | string,
        currencyId: null | string,
        versionId: null | string,
    }
}

const ContextStore: Module<ContextState, VuexRootState> = {
    namespaced: true,

    state: (): ContextState => ({
        app: {
            config: {
                adminWorker: null,
                bundles: null,
                version: null,
                versionRevision: null,
            },
            environment: null,
            fallbackLocale: null,
            features: null,
            firstRunWizard: null,
            systemCurrencyId: null,
            systemCurrencyISOCode: null,
            disableExtensions: false,
            lastActivity: 0,
        },
        api: {
            apiPath: null,
            apiResourcePath: null,
            assetsPath: null,
            authToken: null,
            basePath: null,
            pathInfo: null,
            inheritance: null,
            installationPath: null,
            languageId: null,
            language: null,
            apiVersion: null,
            liveVersionId: null,
            systemLanguageId: null,
            currencyId: null,
            versionId: null,
        },
    }),

    mutations: {
        setApiApiPath(state, value: string) {
            state.api.apiPath = value;
        },

        setApiApiResourcePath(state, value: string) {
            state.api.apiResourcePath = value;
        },

        setApiAssetsPath(state, value: string) {
            state.api.assetsPath = value;
        },

        setApiAuthToken(state, value: ApiAuthToken) {
            state.api.authToken = value;
        },

        setApiInheritance(state, value: boolean) {
            state.api.inheritance = value;
        },

        setApiInstallationPath(state, value: string) {
            state.api.installationPath = value;
        },

        setApiLanguage(state, value: { name: string }) {
            state.api.language = value;
        },

        setApiApiVersion(state, value: string) {
            state.api.apiVersion = value;
        },

        setApiLiveVersionId(state, value: string) {
            state.api.liveVersionId = value;
        },

        setApiSystemLanguageId(state, value: string) {
            state.api.systemLanguageId = value;
        },

        setAppEnvironment(state, value: 'development'|'production'|'testing') {
            state.app.environment = value;
        },

        setAppFallbackLocale(state, value: string) {
            state.app.fallbackLocale = value;
        },

        setAppFeatures(state, value: { [featureKey: string]: boolean}) {
            state.app.features = value;
        },

        setAppFirstRunWizard(state, value: boolean) {
            state.app.firstRunWizard = value;
        },

        setAppSystemCurrencyId(state, value: string) {
            state.app.systemCurrencyId = value;
        },

        setAppSystemCurrencyISOCode(state, value: string) {
            state.app.systemCurrencyISOCode = value;
        },

        setAppConfigAdminWorker(state, value: {
            enableAdminWorker: boolean,
            enableQueueStatsWorker: boolean,
            enableNotificationWorker: boolean,
            transports: string[]
        }) {
            state.app.config.adminWorker = value;
        },

        setAppConfigBundles(state, value: {
            [BundleName: string]: {
                css: string | string[],
                js: string | string[],
                permissions: privileges,
            }
        }) {
            state.app.config.bundles = value;
        },

        setAppConfigVersion(state, value: string) {
            state.app.config.version = value;
        },

        setAppConfigVersionRevision(state, value: string) {
            state.app.config.versionRevision = value;
        },

        addAppValue<K extends keyof ContextState['app']>(
            state: ContextState,
            { key, value }: { key: K, value: ContextState['app'][K] },
        ) {
            if (value === 'true') {
                state.app[key] = true as ContextState['app'][K];

                return;
            }

            if (value === 'false') {
                state.app[key] = false as ContextState['app'][K];

                return;
            }

            state.app[key] = value;
        },

        addApiValue<K extends keyof ContextState['api']>(
            state: ContextState,
            { key, value }: { key: K, value: ContextState['api'][K] },
        ) {
            state.api[key] = value;
        },

        addAppConfigValue<K extends keyof ContextState['app']['config']>(
            state: ContextState,
            { key, value }: { key: K, value: ContextState['app']['config'][K] },
        ) {
            state.app.config[key] = value;
        },

        setApiLanguageId(state, newLanguageId: string) {
            state.api.languageId = newLanguageId;
            localStorage.setItem('sw-admin-current-language', newLanguageId);
        },

        resetLanguageToDefault(state) {
            state.api.languageId = state.api.systemLanguageId;
        },
    },

    getters: {
        isSystemDefaultLanguage(state) {
            return state.api.languageId === state.api.systemLanguageId;
        },
    },
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ContextStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { ContextState };
