/**
 * @package admin
 * @private
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */
export default {
    state: {
        locales: [],
    },

    mutations: {
        registerAdminLocale(state, locale) {
            if (state.locales.find((l) => l === locale)) {
                return;
            }

            state.locales.push(locale);
        },
    },
};
