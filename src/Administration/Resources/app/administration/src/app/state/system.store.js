/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
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
