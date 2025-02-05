import type { DiscountCampaign } from '../../module/sw-extension/service/extension-store-action.service';

/**
 * @sw-package framework
 */
interface CampaignComponent {
    content: {
        description: {
            [key: string]: string;
        };
    };
}
interface Campaign {
    name: string;
    components?: Record<string, CampaignComponent>;
    startDate?: string;
    endDate?: string | null;
}

const marketingStore = Shopware.Store.register({
    id: 'marketing',

    state: () => ({
        campaign: {} as Campaign | object,
    }),

    actions: {
        setCampaign(campaign: Campaign) {
            this.campaign = campaign;

            // create translations for campaign description text
            const translations: {
                [langIsoCode: string]: {
                    marketing?: {
                        [componentName: string]: {
                            content?: {
                                description?: {
                                    text?: string;
                                };
                            };
                        };
                    };
                };
            } = {};

            if (!campaign.components) {
                return;
            }

            Object.entries(campaign.components).forEach(
                ([
                    componentName,
                    config,
                ]) => {
                    const descriptionText = config?.content?.description?.text;

                    if (descriptionText) {
                        Object.entries(descriptionText).forEach(
                            ([
                                langIsoCode,
                                snippet,
                            ]) => {
                                translations[langIsoCode] ??= {};
                                translations[langIsoCode].marketing ??= {};
                                translations[langIsoCode].marketing[componentName] ??= {};
                                translations[langIsoCode].marketing[componentName].content ??= {};
                                translations[langIsoCode].marketing[componentName].content.description ??= {};
                                translations[langIsoCode].marketing[componentName].content.description.text = snippet;
                            },
                        );
                    }
                },
            );

            // add translations to i18n messages

            Object.entries(translations).forEach(
                ([
                    langIsoCode,
                    snippets,
                ]) => {
                    Shopware.Snippet.mergeLocaleMessage(langIsoCode, snippets);
                },
            );
        },

        getActiveCampaignDataForComponent(componentName: string): {
            component: object | null;
            campaignName: string | undefined;
        } {
            return {
                component: this.getActiveCampaign?.components?.[componentName] ?? null,
                campaignName: this.getActiveCampaign?.name,
            };
        },
    },

    getters: {
        getActiveCampaign(): null | Campaign {
            // eslint-disable-next-line max-len
            if (
                Shopware.Service('shopwareDiscountCampaignService')?.isDiscountCampaignActive(
                    this.campaign as DiscountCampaign,
                )
            ) {
                return this.campaign as Campaign;
            }

            return null;
        },
    },
});

/**
 * @private
 */
export type MarketingStore = ReturnType<typeof marketingStore>;

/**
 * @private
 */
export default marketingStore;
