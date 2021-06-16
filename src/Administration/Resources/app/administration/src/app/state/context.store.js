const storeState = {
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
    },
    api: {
        apiPath: null,
        apiResourcePath: null,
        assetsPath: null,
        authToken: null,
        inheritance: null,
        installationPath: null,
        languageId: null,
        language: null,
        apiVersion: null,
        liveVersionId: null,
        systemLanguageId: null,
    },
};

export default {
    namespaced: true,

    state: storeState,

    mutations: {
        ...createMutationsForState(storeState, null),

        addAppValue(state, { key, value }) {
            state.app[key] = value;
        },

        addApiValue(state, { key, value }) {
            state.api[key] = value;
        },

        addAppConfigValue(state, { key, value }) {
            state.app.config[key] = value;
        },

        setApiLanguageId(state, newLanguageId) {
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

function createMutationsForState(stateValues, parent) {
    return Object.entries(stateValues).reduce((acc, [key, value]) => {
        const path = parent ? `${parent}.${key}` : key;

        const pathCamelCase = path.split('.').reduce((fullPath, pathPart) => {
            fullPath += pathPart.charAt(0).toUpperCase() + pathPart.slice(1);
            return fullPath;
        }, '');

        const mutationName = `set${pathCamelCase}`;

        if (value === null) {
            acc[mutationName] = createMutationFunction(path);
        } else {
            acc = { ...acc, ...createMutationsForState(value, path) };
        }

        return acc;
    }, {});
}

function createMutationFunction(path) {
    const _set = Shopware.Utils.object.set;

    return (state, payload) => {
        return _set(state, path, payload);
    };
}
