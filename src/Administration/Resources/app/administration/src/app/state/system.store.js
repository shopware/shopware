/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
