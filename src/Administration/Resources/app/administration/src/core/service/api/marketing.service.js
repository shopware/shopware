import ApiService from 'src/core/service/api.service';

export default class MarketingService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'marketing');
        this.name = 'marketingService';
    }

    getActiveDiscountCampaigns() {
        // to enable the mock you need to disable this line
        return Promise.resolve({});

        // return mock value instead of value from the SBP
        // eslint-disable-next-line no-unreachable
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
                    name: 'An example campaign',
                    title: 'string',
                    phase: 'comingSoonPhase',
                    comingSoonStartDate: '2005-08-15T15:52:01',
                    startDate: '2005-08-15T15:52:01',
                    lastCallStartDate: '2005-08-15T15:52:01',
                    endDate: '2025-08-15T15:52:01',
                    components: {
                        storeBanner: {
                            background: {
                                color: '#ffffff',
                                // eslint-disable-next-line max-len
                                image: 'https://images.unsplash.com/photo-1518695075031-b83a29bf0012?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1050&q=80',
                                position: '50% 70%',
                            },
                            content: {
                                textColor: '#fff',
                                headline: {
                                    'de-DE': 'Super Angebot',
                                    'en-GB': 'Amazing offer',
                                },
                                description: {
                                    'de-DE': 'Günstiger geht es nicht',
                                    'en-GB': 'It will not get cheaper',
                                },
                                cta: {
                                    category: 'GitHub',
                                    text: {
                                        'de-DE': 'Zeige GitHub',
                                        'en-GB': 'Show GitHub',
                                    },
                                },
                            },
                        },
                        dashboardBanner: {
                            background: {
                                color: '#ffffff',
                                // eslint-disable-next-line max-len
                                image: 'https://images.unsplash.com/photo-1493606278519-11aa9f86e40a?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80',
                                position: '100% 75%',
                            },
                            leftImage: {
                                src: {
                                    // eslint-disable-next-line max-len
                                    'en-GB': 'https://images.unsplash.com/photo-1587049016823-69ef9d68bd44?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80',
                                    // eslint-disable-next-line max-len
                                    'de-DE': 'https://images.unsplash.com/photo-1527866959252-deab85ef7d1b?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1050&q=80',
                                },
                                bgColor: '#ffffff',
                                hideInSmallViewports: false,
                                srcset: {
                                    // eslint-disable-next-line max-len
                                    'en-GB': 'https://images.unsplash.com/photo-1587049016823-69ef9d68bd44?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80 634w',
                                    // eslint-disable-next-line max-len
                                    'de-DE': 'https://images.unsplash.com/photo-1527866959252-deab85ef7d1b?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1050&q=80 1050w',
                                },
                            },
                            content: {
                                textColor: '#171717',
                                linkColor: '#26af44',
                                headline: {
                                    'de-DE': 'Tolle Kampagne',
                                    'en-GB': 'Amazing campaign',
                                },
                                description: {
                                    text: {
                                        'de-DE': 'Es ist {goToShopwareHomePage}, öffne den {goToExtensionStoreAndOpenCategory} oder gehe zum {goToExtensionStore}',
                                        'en-GB': 'Its {goToShopwareHomePage}, open {goToExtensionStoreAndOpenCategory} or go to the {goToExtensionStore}',
                                    },
                                    inlineActions: [
                                        {
                                            placeholder: 'goToExtensionStore',
                                            text: {
                                                'de-DE': 'Erweiterungs Store',
                                                'en-GB': 'Extension Store',
                                            },
                                            route: 'sw.extension.store.index.extensions',
                                        },
                                        {
                                            placeholder: 'goToExtensionStoreAndOpenCategory',
                                            text: {
                                                'de-DE': 'Sommer Sale',
                                                'en-GB': 'Summer Sale',
                                            },
                                            execution: {
                                                method: 'linkToExtensionStoreAndSelectCategory',
                                                arguments: ['category', 'summerSale'],
                                            },
                                        },
                                        {
                                            placeholder: 'goToShopwareHomePage',
                                            text: {
                                                'de-DE': 'Shopware',
                                                'en-GB': 'Shopware',
                                            },
                                            externalLink: {
                                                'de-DE': 'https://www.shopware.de',
                                                'en-GB': 'https://www.shopware.com',
                                            },
                                        },
                                    ],
                                },
                                label: {
                                    bgColor: '#ac2c2c',
                                    textColor: '#ffffff',
                                    text: {
                                        'de-DE': 'Wichtig',
                                        'en-GB': 'Important',
                                    },
                                },
                                mainAction: {
                                    buttonVariant: 'ghost',
                                    bannerIsClickable: true,
                                    cta: {
                                        'de-DE': 'Kampagne öffnen',
                                        'en-GB': 'Open campaign',
                                    },
                                    execution: {
                                        method: 'linkToExtensionStoreAndSelectCategory',
                                        arguments: ['category', 'summerSale'],
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
