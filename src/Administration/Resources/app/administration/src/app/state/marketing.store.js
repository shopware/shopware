// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
                        translations[langIsoCode] ??= {};
                        translations[langIsoCode].marketing ??= {};
                        translations[langIsoCode].marketing[componentName] ??= {};
                        translations[langIsoCode].marketing[componentName].content ??= {};
                        translations[langIsoCode].marketing[componentName].content.description ??= {};
                        translations[langIsoCode].marketing[componentName].content.description.text = snippet;
                    });
                }
            });

            // add translations to i18n messages
            const { i18n } = Shopware.Application.view;

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
                return {
                    component: getters.getActiveCampaign?.components?.[componentName] ?? null,
                    campaignName: getters.getActiveCampaign?.name,
                };
            };
        },
    },
};
