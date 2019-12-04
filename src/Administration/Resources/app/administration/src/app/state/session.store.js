const { Application } = Shopware;
const debug = Shopware.Utils.debug;

export default {
    state: {
        currentUser: null,
        languageId: '', // move me to session/currentLanguageId
        currentLocale: null // move me to session/currentLanguageId
    },

    getters: {
        adminLocaleLanguage(state) {
            if (!state || !state.currentLocale) {
                return null;
            }

            return state.currentLocale.split('-')[0];
        },

        adminLocaleRegion(state) {
            if (!state || !state.currentLocale) {
                return null;
            }
            return state.currentLocale.split('-')[1];
        }
    },

    actions: {
        setAdminLocale({ commit, rootState }, locale) {
            const locales = rootState.system.locales;
            const loginService = Shopware.Service('loginService');

            if (!loginService.isLoggedIn()) {
                commit('setAdminLocale', { locales, locale, languageId: '' });
                return;
            }

            const localeToLanguageService = Shopware.Service('localeToLanguageService');
            localeToLanguageService.localeToLanguage(locale).then((languageId) => {
                commit('setAdminLocale', { locales, locale, languageId });
            });
        }
    },

    mutations: {
        setCurrentUser(state, user) {
            state.currentUser = user;
        },

        removeCurrentUser(state) {
            state.currentUser = null;
        },

        setAdminLocale(state, { locales, locale, languageId }) {
            if (!locales.find((l) => l === locale)) {
                debug.warn('SessionStore', `Locale ${locale} not registered at store`);
                return;
            }

            state.languageId = languageId;
            state.currentLocale = locale;

            Application.getContainer('factory').locale.storeCurrentLocale(state.currentLocale);
        }
    }
};
