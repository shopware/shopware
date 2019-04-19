import { Application } from 'src/core/shopware';
import { debug } from 'src/core/service/util.service';

export default {
    state: {
        locales: [],
        currentLocale: Application.getContainer('factory').locale.getLastKnownLocale(),
        fallbackLocale: null
    },

    getters: {
        adminLocaleLanguage(state) {
            return state.currentLocale.split('-')[0];
        },

        adminLocaleRegion(state) {
            return state.currentLocale.split('-')[1];
        }
    },

    mutations: {
        registerAdminLocale(state, locale) {
            if (state.locales.find((l) => l === locale)) {
                return;
            }

            state.locales.push(locale);
            if (state.locales.length === 1) {
                state.currentLocale = locale;
            }
        },

        setAdminLocale(state, locale) {
            if (!state.locales.find((l) => l === locale)) {
                debug.warn('AdminLocaleStore', `Locale ${locale} not registered at store`);
                return;
            }

            state.currentLocale = locale;
            Application.getContainer('factory').locale.storeCurrentLocale(state.currentLocale);
        },

        setAdminFallbackLocale(state, locale) {
            if (!state.locales.find((l) => l === locale)) {
                debug.warn('AdminLocaleStore', `Locale ${locale} not registered at store`);
                return;
            }

            state.fallbackLocale = locale;
        }
    }
};
