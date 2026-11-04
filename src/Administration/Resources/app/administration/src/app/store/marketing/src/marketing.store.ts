/**
 * @sw-package framework
 */
import {
    defineStoreOptions,
    type DiscountCampaign,
    getShopwareDiscountCampaignService,
    getSnippet,
    getStore,
    type StoreOf,
} from '../dependencies';

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

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const marketingStoreId = 'marketing';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const marketingStoreOptions = defineStoreOptions({
    id: marketingStoreId,

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

            Object.entries(translations).forEach(([langIsoCode, snippets]) => {
                getSnippet()?.mergeLocaleMessage(langIsoCode, snippets);
            });
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
            if (getShopwareDiscountCampaignService()?.isDiscountCampaignActive(this.campaign as DiscountCampaign)) {
                return this.campaign as Campaign;
            }

            return null;
        },
    },
});

/**
 * @public
 */
export type MarketingStore = StoreOf<typeof marketingStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useMarketingStore = (): MarketingStore => getStore(marketingStoreId);
