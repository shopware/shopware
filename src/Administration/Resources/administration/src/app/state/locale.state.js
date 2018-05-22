import { State } from 'src/core/shopware';

/**
 * @module app/state/locale
 */
State.register('locale', {
    namespaced: true,

    state() {
        return {
            locale: 'en-GB'
        };
    },

    mutations: {
        setLocale(state, locale) {
            const factoryContainer = Shopware.Application.getContainer('factory');
            const localeFactory = factoryContainer.locale;

            state.locale = locale;
            localeFactory.setLocale(locale);
        }
    }
});
