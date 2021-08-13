import { mount, createLocalVue } from '@vue/test-utils';
import VueI18n from 'vue-i18n';
import 'src/app/component/campaign/sw-campaign-banner';
import 'src/app/component/campaign/sw-campaign-property-mapping';
import 'src/app/component/base/sw-button';
import ShopwareDiscountCampaignService from 'src/app/service/discount-campaign.service';
import extensionStore from 'src/module/sw-extension/store/extensions.store';

let i18n;

/**
 * This test is in integrative test which combines
 * - sw-campaign-banner
 * - sw-campaign-property-mapping
 * - marketing.store
 */

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(i18n);

    return mount({
        localVue,
        i18n,
        template: `
<div>
    <sw-campaign-property-mapping component-name="dashboardBanner">
        <template #default="{ mappedProperties }">
            <sw-campaign-banner
                v-if="mappedProperties"
                v-bind="mappedProperties"
            />
        </template>
    </sw-campaign-property-mapping>
</div>
        `
    }, {
        stubs: {
            'sw-campaign-property-mapping': Shopware.Component.build('sw-campaign-property-mapping'),
            'sw-campaign-banner': Shopware.Component.build('sw-campaign-banner'),
            'sw-meteor-card': Shopware.Component.build('sw-meteor-card'),
            'sw-button': Shopware.Component.build('sw-button')
        }
    });
}

function createExampleCampaign() {
    return {
        name: 'string',
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
                    image: 'http://www.company.org/cum/sonoras',
                    position: 'string'
                },
                content: {
                    textColor: '#000000',
                    headline: {
                        'de-DE': 'string (max 40 Zeichen)',
                        'en-GB': 'string (max 40 characters)'
                    },
                    description: {
                        'de-DE': 'string (max 90 Zeichen)',
                        'en-GB': 'string (max 90 characters)'
                    },
                    cta: {
                        category: 'CategoryXY',
                        'de-DE': 'string (max 40 Zeichen)',
                        'en-GB': 'string (max 40 characters)'
                    }
                }
            },
            dashboardBanner: {
                background: {
                    color: '#ffffff',
                    // eslint-disable-next-line max-len
                    image: 'https://images.unsplash.com/photo-1493606278519-11aa9f86e40a?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80',
                    position: '100% 75%'
                },
                leftImage: {
                    src: {
                        // eslint-disable-next-line max-len
                        'en-GB': 'https://images.unsplash.com/photo-1587049016823-69ef9d68bd44?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80',
                        // eslint-disable-next-line max-len
                        'de-DE': 'https://images.unsplash.com/photo-1527866959252-deab85ef7d1b?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1050&q=80'
                    },
                    bgColor: '#ffffff',
                    hideInSmallViewports: false,
                    srcset: {
                        // eslint-disable-next-line max-len
                        'en-GB': 'https://images.unsplash.com/photo-1587049016823-69ef9d68bd44?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80 634w',
                        // eslint-disable-next-line max-len
                        'de-DE': 'https://images.unsplash.com/photo-1527866959252-deab85ef7d1b?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1050&q=80 1050w'
                    }
                },
                content: {
                    textColor: '#171717',
                    linkColor: '#26af44',
                    headline: {
                        'de-DE': 'Tolle Kampagne',
                        'en-GB': 'Amazing campaign'
                    },
                    description: {
                        text: {
                            'de-DE': 'Es ist {goToShopwareHomePage}, öffne den {goToExtensionStoreAndOpenCategory} oder gehe zum {goToExtensionStore}',
                            'en-GB': 'Its {goToShopwareHomePage}, open {goToExtensionStoreAndOpenCategory} or go to the {goToExtensionStore}'
                        },
                        inlineActions: [
                            {
                                placeholder: 'goToExtensionStore',
                                text: {
                                    'de-DE': 'Erweiterungs Store',
                                    'en-GB': 'Extension Store'
                                },
                                route: 'sw.extension.store.index.extensions'
                            },
                            {
                                placeholder: 'goToExtensionStoreAndOpenCategory',
                                text: {
                                    'de-DE': 'Sommer Sale',
                                    'en-GB': 'Summer Sale'
                                },
                                execution: {
                                    method: 'linkToExtensionStoreAndSelectCategory',
                                    arguments: ['category', 'summerSale2021']
                                }
                            },
                            {
                                placeholder: 'goToShopwareHomePage',
                                text: {
                                    'de-DE': 'Shopware',
                                    'en-GB': 'Shopware'
                                },
                                externalLink: {
                                    'de-DE': 'https://www.shopware.de',
                                    'en-GB': 'https://www.shopware.com'
                                }
                            }
                        ]
                    },
                    label: {
                        bgColor: '#ac2c2c',
                        textColor: '#ffffff',
                        text: {
                            'de-DE': 'Wichtig',
                            'en-GB': 'Important'
                        }
                    },
                    mainAction: {
                        buttonVariant: 'primary',
                        bannerIsClickable: false,
                        cta: {
                            'de-DE': 'Kampagne öffnen',
                            'en-GB': 'Open campaign'
                        },
                        execution: {
                            method: 'linkToExtensionStoreAndSelectCategory',
                            arguments: ['category', 'summerSale2021']
                        }
                    }
                }
            }
        }
    };
}

describe('src/app/component/campaign/sw-campaign-banner', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_12608'];

        // import depedency async because the component is behind a feature flag
        await import('src/app/component/meteor/sw-meteor-card');

        Shopware.Service().register('shopwareDiscountCampaignService', () => {
            return new ShopwareDiscountCampaignService();
        });

        // add extensionsStore
        Shopware.State.registerModule('shopwareExtensions', extensionStore);
    });

    beforeEach(() => {
        // add spy to window.open
        jest.spyOn(window, 'open').mockImplementation(() => {});
        // reset campaign
        Shopware.State.commit('marketing/setCampaign', {});
        // reset translations
        i18n = new VueI18n({ locale: 'en-GB' });
        Shopware.Application.view.i18n = i18n;
        // reset extensionsStore search
        Shopware.State.get('shopwareExtensions').search = extensionStore.state().search;
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should not be visible when no marketing campaign exists', async () => {
        wrapper = createWrapper();

        expect(wrapper.find('.sw-campaign-banner').exists()).toBe(false);
    });

    it('should be visible when marketing campaign exists', async () => {
        Shopware.State.commit('marketing/setCampaign', createExampleCampaign());

        wrapper = createWrapper();

        expect(wrapper.find('.sw-campaign-banner').exists()).toBe(true);
    });

    it('should map background correctly', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.background.image = 'http://my-background/image/';
        campaign.components.dashboardBanner.background.position = '80% 20%';

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();

        // check background image
        const bannerContainer = wrapper.find('.sw-campaign-banner__container');
        expect(bannerContainer.attributes().style).toContain('background-image: url(http://my-background/image/);');
        expect(bannerContainer.attributes().style).toContain('background-position: 80% 20%;');
        expect(bannerContainer.attributes().style).toContain('background-repeat: no-repeat;');
        expect(bannerContainer.attributes().style).toContain('background-size: cover;');
    });

    it('should fallback to background color when image is not set', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.background.image = undefined;
        campaign.components.dashboardBanner.background.color = 'rgb(123, 255, 123)';

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();

        // check background color
        const bannerContainer = wrapper.find('.sw-campaign-banner__container');
        expect(bannerContainer.attributes().style).toContain('background-color: rgb(123, 255, 123);');
    });

    it('should map left image correctly', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.leftImage.bgColor = 'rgb(160, 240, 130)';
        campaign.components.dashboardBanner.leftImage.src = {
            'en-GB': 'https://image.shopware.com/example/en-GB'
        };
        campaign.components.dashboardBanner.leftImage.srcset = {
            'en-GB': 'https://image.shopware.com/example/en-GB/large'
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();

        // check left image mapping
        const leftImageWrapper = wrapper.find('.sw-campaign-banner__image');
        expect(leftImageWrapper.attributes().style).toBe('background: rgb(160, 240, 130);');

        const leftImage = leftImageWrapper.find('img');
        expect(leftImage.attributes().src).toBe('https://image.shopware.com/example/en-GB');
        expect(leftImage.attributes().srcset).toBe('https://image.shopware.com/example/en-GB/large');
    });

    it('should now show label when it is not defined', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.label.text = undefined;

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();

        const label = wrapper.find('.sw-campaign-banner__label');
        expect(label.exists()).toBe(false);
    });

    it('should map the label correctly', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.label.bgColor = 'rgb(255, 0, 0)';
        campaign.components.dashboardBanner.content.label.textColor = 'rgb(255, 254, 255)';
        campaign.components.dashboardBanner.content.label.text = {
            'en-GB': 'Awesome label'
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();

        const label = wrapper.find('.sw-campaign-banner__label');
        expect(label.text()).toContain('Awesome label');
        expect(label.attributes().style).toContain('background: rgb(255, 0, 0);');
        expect(label.attributes().style).toContain('color: rgb(255, 254, 255);');
    });

    it('should map the headline correctly', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.textColor = 'rgb(0, 255, 0)';
        campaign.components.dashboardBanner.content.headline = {
            'en-GB': 'My awesome headline'
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();

        const headline = wrapper.find('h3');
        expect(headline.text()).toContain('My awesome headline');
        expect(headline.attributes().style).toContain('color: rgb(0, 255, 0);');
    });

    it('should map the description correctly', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.textColor = 'rgb(255, 0, 0)';
        campaign.components.dashboardBanner.content.linkColor = 'rgb(123, 0, 123)';
        campaign.components.dashboardBanner.description = {
            text: {
                'en-GB': 'Its {goToShopwareHomePage}, open {goToExtensionStoreAndOpenCategory} or go to the {goToExtensionStore}'
            },
            inlineActions: [
                {
                    placeholder: 'goToExtensionStore',
                    text: {
                        'en-GB': 'Extension Store'
                    },
                    route: 'sw.extension.store.index.extensions'
                },
                {
                    placeholder: 'goToExtensionStoreAndOpenCategory',
                    text: {
                        'en-GB': 'Summer Sale'
                    },
                    execution: {
                        method: 'linkToExtensionStoreAndSelectCategory',
                        arguments: ['category', 'summerSale2021']
                    }
                },
                {
                    placeholder: 'goToShopwareHomePage',
                    text: {
                        'en-GB': 'Shopware'
                    },
                    externalLink: {
                        'en-GB': 'https://www.shopware.com'
                    }
                }
            ]
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();

        const description = wrapper.find('p');
        expect(description.attributes().style).toContain('color: rgb(255, 0, 0)');

        // replace tabs, newlines, etc
        const descriptionText = description.text().replace(/\s\s+/g, ' ');
        expect(descriptionText).toEqual('Its Shopware, open Summer Sale or go to the Extension Store');

        // check component interpolation slots
        const actionGoToShopwareHomepage = description.find('.sw-campaign-banner__description-action-goToShopwareHomePage');
        const actionGoToExtensionStoreAndOpenCategory = description.find('.sw-campaign-banner__description-action-goToExtensionStoreAndOpenCategory');
        const actionGoToExtensionStore = description.find('.sw-campaign-banner__description-action-goToExtensionStore');

        expect(actionGoToShopwareHomepage.exists()).toBe(true);
        expect(actionGoToShopwareHomepage.text()).toEqual('Shopware');

        expect(actionGoToExtensionStoreAndOpenCategory.exists()).toBe(true);
        expect(actionGoToExtensionStoreAndOpenCategory.text()).toEqual('Summer Sale');

        expect(actionGoToExtensionStore.exists()).toBe(true);
        expect(actionGoToExtensionStore.text()).toEqual('Extension Store');

        // check styling of links
        [
            actionGoToShopwareHomepage,
            actionGoToExtensionStoreAndOpenCategory,
            actionGoToExtensionStore
        ].forEach(action => {
            expect(action.element.tagName).toBe('A');
            expect(action.attributes().style).toContain('color: rgb(123, 0, 123)');
        });
    });

    it('should map the description actions correctly [external link]', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.textColor = 'rgb(255, 0, 0)';
        campaign.components.dashboardBanner.content.linkColor = 'rgb(123, 0, 123)';
        campaign.components.dashboardBanner.description = {
            text: {
                'en-GB': 'Its {goToShopwareHomePage}, open {goToExtensionStoreAndOpenCategory} or go to the {goToExtensionStore}'
            },
            inlineActions: [
                {
                    placeholder: 'goToExtensionStore',
                    text: {
                        'en-GB': 'Extension Store'
                    },
                    route: 'sw.extension.store.index.extensions'
                },
                {
                    placeholder: 'goToExtensionStoreAndOpenCategory',
                    text: {
                        'en-GB': 'Summer Sale'
                    },
                    execution: {
                        method: 'linkToExtensionStoreAndSelectCategory',
                        arguments: ['category', 'summerSale2021']
                    }
                },
                {
                    placeholder: 'goToShopwareHomePage',
                    text: {
                        'en-GB': 'Shopware'
                    },
                    externalLink: {
                        'en-GB': 'https://www.shopware.com'
                    }
                }
            ]
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();

        const description = wrapper.find('p');
        const actionGoToShopwareHomepage = description.find('.sw-campaign-banner__description-action-goToShopwareHomePage');

        // open external link
        expect(window.open).not.toHaveBeenCalled();
        await actionGoToShopwareHomepage.trigger('click');
        expect(window.open).toHaveBeenCalledWith('https://www.shopware.com');
        window.open.mockClear();
    });

    it('should map the description actions correctly [execution]', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.textColor = 'rgb(255, 0, 0)';
        campaign.components.dashboardBanner.content.linkColor = 'rgb(123, 0, 123)';
        campaign.components.dashboardBanner.description = {
            text: {
                'en-GB': 'Its {goToShopwareHomePage}, open {goToExtensionStoreAndOpenCategory} or go to the {goToExtensionStore}'
            },
            inlineActions: [
                {
                    placeholder: 'goToExtensionStore',
                    text: {
                        'en-GB': 'Extension Store'
                    },
                    route: 'sw.extension.store.index.extensions'
                },
                {
                    placeholder: 'goToExtensionStoreAndOpenCategory',
                    text: {
                        'en-GB': 'Summer Sale'
                    },
                    execution: {
                        method: 'linkToExtensionStoreAndSelectCategory',
                        arguments: ['category', 'summerSale2021']
                    }
                },
                {
                    placeholder: 'goToShopwareHomePage',
                    text: {
                        'en-GB': 'Shopware'
                    },
                    externalLink: {
                        'en-GB': 'https://www.shopware.com'
                    }
                }
            ]
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();

        const description = wrapper.find('p');
        const actionGoToExtensionStoreAndOpenCategory = description.find('.sw-campaign-banner__description-action-goToExtensionStoreAndOpenCategory');

        // go to store and open category
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(0);
        expect(Shopware.State.get('shopwareExtensions').search.filter).toEqual({});
        await actionGoToExtensionStoreAndOpenCategory.trigger('click');
        expect(Shopware.State.get('shopwareExtensions').search.filter).toEqual({
            category: 'summerSale2021'
        });
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(1);
        expect(wrapper.vm.$router.push.mock.calls[0]).toEqual([{
            name: 'sw.extension.store.listing.app'
        }]);
    });

    it('should map the description actions correctly [route]', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.textColor = 'rgb(255, 0, 0)';
        campaign.components.dashboardBanner.content.linkColor = 'rgb(123, 0, 123)';
        campaign.components.dashboardBanner.description = {
            text: {
                'en-GB': 'Its {goToShopwareHomePage}, open {goToExtensionStoreAndOpenCategory} or go to the {goToExtensionStore}'
            },
            inlineActions: [
                {
                    placeholder: 'goToExtensionStore',
                    text: {
                        'en-GB': 'Extension Store'
                    },
                    route: 'sw.extension.store.index.extensions'
                },
                {
                    placeholder: 'goToExtensionStoreAndOpenCategory',
                    text: {
                        'en-GB': 'Summer Sale'
                    },
                    execution: {
                        method: 'linkToExtensionStoreAndSelectCategory',
                        arguments: ['category', 'summerSale2021']
                    }
                },
                {
                    placeholder: 'goToShopwareHomePage',
                    text: {
                        'en-GB': 'Shopware'
                    },
                    externalLink: {
                        'en-GB': 'https://www.shopware.com'
                    }
                }
            ]
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();

        const description = wrapper.find('p');
        const actionGoToExtensionStore = description.find('.sw-campaign-banner__description-action-goToExtensionStore');

        // go to store
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(0);
        await actionGoToExtensionStore.trigger('click');
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(1);
        expect(wrapper.vm.$router.push.mock.calls[0]).toEqual([{
            name: 'sw.extension.store.index.extensions'
        }]);
    });

    it('should map the main action correctly [external link]', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.mainAction = {
            buttonVariant: 'primary',
            bannerIsClickable: false,
            cta: {
                'en-GB': 'Open campaign'
            },
            externalLink: {
                'en-GB': 'https://www.shopware.com/campaign-link'
            }
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();
        const mainAction = wrapper.find('.sw-campaign-banner__action');
        expect(mainAction.text()).toEqual('Open campaign');

        expect(window.open).not.toHaveBeenCalled();
        await mainAction.find('button').trigger('click');
        expect(window.open).toHaveBeenCalledWith('https://www.shopware.com/campaign-link');
    });

    it('should map the main action correctly [execution] with method "linkToExtensionStoreAndSelectCategory"', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.mainAction = {
            buttonVariant: 'primary',
            bannerIsClickable: false,
            cta: {
                'en-GB': 'Open campaign'
            },
            execution: {
                method: 'linkToExtensionStoreAndSelectCategory',
                arguments: ['category', 'summerSale2021']
            }
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();
        const mainAction = wrapper.find('.sw-campaign-banner__action');
        expect(mainAction.text()).toEqual('Open campaign');

        // go to store and open category
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(0);
        expect(Shopware.State.get('shopwareExtensions').search.filter).toEqual({});
        await mainAction.find('button').trigger('click');
        expect(Shopware.State.get('shopwareExtensions').search.filter).toEqual({
            category: 'summerSale2021'
        });
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(1);
        expect(wrapper.vm.$router.push.mock.calls[0]).toEqual([{
            name: 'sw.extension.store.listing.app'
        }]);
    });

    it('should map the main action correctly [execution] with method "showBookingOptions"', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.mainAction = {
            buttonVariant: 'primary',
            bannerIsClickable: false,
            cta: {
                'en-GB': 'Show booking options'
            },
            execution: {
                method: 'showBookingOptions'
            }
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();
        const mainAction = wrapper.find('.sw-campaign-banner__action');
        expect(mainAction.text()).toEqual('Show booking options');

        expect(window.open).not.toHaveBeenCalled();
        await mainAction.find('button').trigger('click');
        expect(window.open).toHaveBeenCalledWith('https://store.shopware.com/en/licenses');
    });

    it('should map the main action correctly [execution] with method "selectBookingOption"', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.mainAction = {
            buttonVariant: 'primary',
            bannerIsClickable: false,
            cta: {
                'en-GB': 'Select extension xy'
            },
            execution: {
                method: 'selectBookingOption',
                arguments: ['id', '9739']
            }
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();
        const mainAction = wrapper.find('.sw-campaign-banner__action');
        expect(mainAction.text()).toEqual('Select extension xy');

        // go to store and open category
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(0);
        await mainAction.find('button').trigger('click');
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(1);
        expect(wrapper.vm.$router.push.mock.calls[0]).toEqual([{
            name: 'sw.extension.store.detail',
            params: { id: '9739' }
        }]);
    });

    it('should not execute main action method "selectBookingOption" with wrong argument', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.mainAction = {
            buttonVariant: 'primary',
            bannerIsClickable: false,
            cta: {
                'en-GB': 'Select extension xy'
            },
            execution: {
                method: 'selectBookingOption',
                arguments: ['id', 'string']
            }
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();
        const mainAction = wrapper.find('.sw-campaign-banner__action');
        expect(mainAction.text()).toEqual('Select extension xy');

        // go to store and open category
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(0);
        await mainAction.find('button').trigger('click');
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(0);
    });

    it('should map the main action correctly [route]', async () => {
        const campaign = createExampleCampaign();
        campaign.components.dashboardBanner.content.mainAction = {
            buttonVariant: 'primary',
            bannerIsClickable: false,
            cta: {
                'en-GB': 'Open campaign'
            },
            route: 'sw.extension.store.index.extensions'
        };

        Shopware.State.commit('marketing/setCampaign', campaign);

        wrapper = createWrapper();
        const mainAction = wrapper.find('.sw-campaign-banner__action');
        expect(mainAction.text()).toEqual('Open campaign');

        // go to store
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(0);
        await mainAction.find('button').trigger('click');
        expect(wrapper.vm.$router.push.mock.calls.length).toEqual(1);
        expect(wrapper.vm.$router.push.mock.calls[0]).toEqual([{
            name: 'sw.extension.store.index.extensions'
        }]);
    });

    [
        {
            buttonVariant: 'primary',
            matchingClasses: ['sw-button', 'sw-button--primary']
        },
        {
            buttonVariant: 'ghost',
            matchingClasses: ['sw-button', 'sw-button--ghost']
        },
        {
            buttonVariant: 'contrast',
            matchingClasses: ['sw-button', 'sw-button--contrast']
        },
        {
            buttonVariant: 'context',
            matchingClasses: ['sw-button', 'sw-button--context']
        },
        {
            buttonVariant: 'default',
            matchingClasses: ['sw-button']
        }
    ].forEach((scenario) => {
        it(`should show the correct main action buttonVariant ${scenario.buttonVariant}`, async () => {
            const campaign = createExampleCampaign();
            campaign.components.dashboardBanner.content.mainAction = {
                buttonVariant: scenario.buttonVariant,
                bannerIsClickable: false,
                cta: {
                    'en-GB': 'Open campaign'
                },
                externalLink: {
                    'en-GB': 'https://www.shopware.com/campaign-link'
                }
            };

            Shopware.State.commit('marketing/setCampaign', campaign);

            wrapper = createWrapper();

            const mainAction = wrapper.find('.sw-campaign-banner__action');

            expect(mainAction.find('button').classes()).toEqual(scenario.matchingClasses);
        });
    });
});
