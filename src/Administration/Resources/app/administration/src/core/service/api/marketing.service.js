import ApiService from 'src/core/service/api.service';

export default class MarketingService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'marketing');
        this.name = 'marketingService';
    }

    getActiveDiscountCampaigns() {
        // return mock value instead of value from the SBP
        return this._getActiveDiscountCampaignsMock();

        // eslint-disable-next-line no-unreachable
        return this.httpClient
            .post(`/${this.getApiBasePath()}/campaigns`, {}, {
                headers: this.basicHeaders(Shopware.Context.api),
            });
    }

    /**
     * This returns a mock discount campaign and should be removed
     * in the final code.
     * @returns {Promise<unknown>}
     * @private
     */
    _getActiveDiscountCampaignsMock() {
        return new Promise(resolve => {
            setTimeout(() => {
                resolve({
                    name: 'string',
                    title: 'string',
                    phase: 'comingSoonPhase',
                    comingSoonStartDate: '2005-08-15T15:52:01',
                    startDate: '2005-08-15T15:52:01',
                    lastCallStartDate: '2005-08-15T15:52:01',
                    endDate: '2005-08-15T15:52:01',
                    components: {
                        storeBanner: {
                            background: {
                                color: '#ffffff',
                                image: 'http://www.company.org/cum/sonoras',
                                position: 'string',
                            },
                            content: {
                                textColor: '#000000',
                                headline: {
                                    'de-DE': 'string (max 40 Zeichen)',
                                    'en-GB': 'string (max 40 characters)',
                                },
                                description: {
                                    'de-DE': 'string (max 90 Zeichen)',
                                    'en-GB': 'string (max 90 characters)',
                                },
                                cta: {
                                    category: 'CategoryXY',
                                    'de-DE': 'string (max 40 Zeichen)',
                                    'en-GB': 'string (max 40 characters)',
                                },
                            },
                        },
                        dashboardBanner: {
                            background: {
                                color: '#ffffff',
                                image: 'http://www.company.org/cum/sonoras',
                                position: 'string',
                            },
                            leftImage: {
                                srcEn: 'http://www.any.org/ventos/verrantque',
                                srcDe: 'http://www.any.org/ventos/verrantque',
                                bgColor: '#ffffff',
                                hideInSmallViewports: false,
                                srcset: {
                                    'de-DE': 'string',
                                    'en-GB': 'string',
                                },
                            },
                            description: {
                                text: {
                                    'de-DE': 'string (max 350 Zeichen)',
                                    'en-GB': 'string (max 350 characters)',
                                },
                                inlineAction: [
                                    {
                                        placeholder: 'goToExtensionStore',
                                        text: {
                                            'de-DE': 'string',
                                            'en-GB': 'string',
                                        },
                                        route: 'sw.extension.store.index.extensions',
                                    },
                                ],
                            },
                            label: {
                                bgColor: '#000000',
                                textColor: '#ffffff',
                                'de-DE': 'string (max 30 Zeichen)',
                                'en-GB': 'string (max 30 characters)',
                            },
                            mainAction: {
                                variant: 'internalLink',
                                bannerIsClickable: false,
                                cta: {
                                    'de-DE': 'string (max 20)',
                                    'en-GB': 'string (max 20)',
                                },
                                execution: {
                                    text: 'linkToExtensionStoreAndSelectCategory',
                                    arguments: {
                                        category: 'CategoryXY',
                                    },
                                },
                            },
                        },
                    },
                });
            }, 1500);
        });
    }
}
