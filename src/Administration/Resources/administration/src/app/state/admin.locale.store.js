import { Application } from 'src/core/shopware';
import { debug } from 'src/core/service/util.service';

export default {
    state: {
        locales: [],
        currentLocale: 'de-DE'
    },

    getters: {
        currentLanguage(state) {
            return state.currentLocale.split('-')[0];
        },

        currentRegion(state) {
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
            Application.getContainer('factory').locale.setLocale(state.currentLocale);
        }
    }
};
