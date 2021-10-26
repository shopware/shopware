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
                transports: string[]
            },
            bundles: null | {
                [BundleName: string]: {
                    css: string | string[],
                    js: string | string[],
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
        language: null | $TSFixMe,
        apiVersion: null | string,
        liveVersionId: null | string,
        systemLanguageId: null | string,
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

        setApiLanguage(state, value: string) {
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
            transports: string[]
        }) {
            state.app.config.adminWorker = value;
        },

        setAppConfigBundles(state, value: {
            [BundleName: string]: {
                css: string | string[],
                js: string | string[],
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

export default ContextStore;
export type { ContextState };
