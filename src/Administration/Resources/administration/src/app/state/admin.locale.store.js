
export default {
    state: {
        locales: [],
        currentLocale: null
    },

    mutations: {
        registerAdminLocale(state, locale) {
            if (state.locales.find((l) => l === locale)) {
                return;
            }

            state.locales.push(locale);

            if (state.currentLocal === null) {
                state.currentLocal = locale;
            }
        },

        setAdminLocale(state, locale) {
            if (state.locales.find((l) => l === locale)) {
                return;
            }

            state.currentLocal = locale;
        }
    }
};
