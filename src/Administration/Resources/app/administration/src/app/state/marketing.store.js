export default {
    namespaced: true,

    state() {
        return {
            campaign: {},
        };
    },

    mutations: {
        setCampaign(state, campaign) {
            state.campaign = campaign;

            // create translations for campaign description text
            const translations = {};

            if (!campaign.components) {
                return;
            }

            Object.entries(campaign.components).forEach(([componentName, config]) => {
                const descriptionText = config?.content?.description?.text;

                if (descriptionText) {
                    Object.entries(descriptionText).forEach(([langIsoCode, snippet]) => {
                        if (!translations[langIsoCode]) {
                            translations[langIsoCode] = {};
                        }

                        if (!translations[langIsoCode].marketing) {
                            translations[langIsoCode].marketing = {};
                        }

                        if (!translations[langIsoCode].marketing[componentName]) {
                            translations[langIsoCode].marketing[componentName] = {};
                        }

                        if (!translations[langIsoCode].marketing[componentName].content) {
                            translations[langIsoCode].marketing[componentName].content = {};
                        }

                        if (!translations[langIsoCode].marketing[componentName].content.description) {
                            translations[langIsoCode].marketing[componentName].content.description = {};
                        }

                        translations[langIsoCode].marketing[componentName].content.description.text = snippet;
                    });
                }
            });

            // add translations to i18n messages
            const i18n = Shopware.Application.view.i18n;

            Object.entries(translations).forEach(([langIsoCode, snippets]) => {
                i18n.mergeLocaleMessage(langIsoCode, snippets);
            });
        },
    },

    getters: {
        getActiveCampaign(state) {
            if (Shopware.Service('shopwareDiscountCampaignService').isDiscountCampaignActive(state.campaign)) {
                return state.campaign;
            }

            return null;
        },

        getActiveCampaignDataForComponent(state, getters) {
            return (componentName) => {
                return getters.getActiveCampaign?.components?.[componentName] ?? null;
            };
        },
    },
};
