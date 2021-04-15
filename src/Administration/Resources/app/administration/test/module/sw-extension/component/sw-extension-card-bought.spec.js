import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-extension-card-base';
import 'src/module/sw-extension/component/sw-extension-card-bought';
import 'src/app/component/context-menu/sw-context-menu-item/';

import ShopwareExtensionService from 'src/module/sw-extension/service/shopware-extension.service';


function createWrapper(extension, license, isLocalAvailable) {
    return shallowMount(Shopware.Component.build('sw-extension-card-bought'), {
        propsData: {
            extension,
            license,
            isLocalAvailable
        },
        mocks: {
            $tc: (v1, v2, v3) => (v1 || v2 ? v1 : JSON.stringify([v1, v2, v3]))
        },
        stubs: {
            'sw-meteor-card': true,
            'sw-switch-field': true,
            'sw-context-button': true,
            'sw-context-menu': true,
            'router-link': {
                template: '<div class="sw-router-link"><slot></slot></div>',
                props: ['to']
            },
            'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item')
        },
        provide: {
            shopwareExtensionService: new ShopwareExtensionService(),
            extensionStoreActionService: {},
            cacheApiService: {}
        }
    });
}

describe('src/module/sw-extension/component/sw-extension-card-base', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        Shopware.Context.api.assetsPath = '';
        Shopware.Utils.debug.warn = () => { };
    });


    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            shortDescription: 'Sample Extension description',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00'
            },
            storeLicense: {
                id: 1095324,
                creationDate: '2021-02-08T15:47:59.000+01:00',
                variant: 'rent',
                netPrice: 23.75,
                nextBookingDate: '2021-03-08T15:47:59.000+01:00',
                licensedExtension: null,
                extensions: [],
                expirationDate: null,
                subscription: null,
                trialPhaseIncluded: true,
                discountInformation: null
            },
            permissions: {},
            images: [{
                remoteLink: 'https://example.com',
                raw: null,
                extensions: []
            }],
            icon: null,
            iconRaw: null,
            active: false,
            type: 'plugin'
        }, {
            licensedExtension: {
                id: 1,
                variant: 'rent',
                netPrice: 497,
                permissions: []
            }
        }, true);
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the extension information', () => {
        wrapper = createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            shortDescription: 'Sample Extension description',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00'
            },
            storeLicense: {
                id: 1095324,
                creationDate: '2021-02-08T15:47:59.000+01:00',
                variant: 'rent',
                netPrice: 23.75,
                nextBookingDate: '2021-03-08T15:47:59.000+01:00',
                licensedExtension: null,
                extensions: [],
                expirationDate: null,
                subscription: null,
                trialPhaseIncluded: true,
                discountInformation: null
            },
            permissions: {},
            icon: 'https://example.com',
            iconRaw: null,
            active: false,
            type: 'plugin'
        }, {
            licensedExtension: {
                id: 1,
                variant: 'rent',
                netPrice: 497,
                permissions: []
            }
        }, true);

        expect(wrapper.find('.sw-extension-card-base__info-name').text()).toBe('Sample Extension Label');
        expect(wrapper.find('.sw-extension-card-base__info-description').text()).toBe('Sample Extension description');
        expect(wrapper.find('.sw-extension-card-base__icon').attributes().src).toBe('https://example.com');
        expect(wrapper.find('.sw-extension-card-base__meta-info').text().replace(/\s/g, '')).toBe('sw-extension-store.component.sw-extension-card-base.installedLabel02/01/2021');
    });

    it('should display a placeholder icon and the rent price', () => {
        wrapper = createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            shortDescription: 'Sample Extension description',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00'
            },
            storeLicense: {
                variants: [{}]
            },
            permissions: {},
            images: [],
            icon: null,
            iconRaw: null,
            active: false,
            type: 'plugin'
        }, {
            licensedExtension: {
                id: 1,
                variant: 'rent',
                netPrice: 497,
                permissions: []
            }
        }, true);

        expect(wrapper.find('.sw-extension-card-base__info-name').text()).toBe('Sample Extension Label');
        expect(wrapper.find('.sw-extension-card-base__info-description').text()).toBe('Sample Extension description');
        expect(wrapper.find('.sw-extension-card-base__icon').attributes().src).toBe('administration/static/img/theme/default_theme_preview.jpg');
        expect(wrapper.find('.sw-extension-card-base__meta-info').text().replace(/\s/g, '')).toBe('sw-extension-store.component.sw-extension-card-base.installedLabel02/01/2021');
    });

    it('should link to the detail page', () => {
        wrapper = createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            shortDescription: 'Sample Extension description',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00'
            },
            storeLicense: {
                variants: [{}]
            },
            permissions: {},
            images: [],
            icon: null,
            iconRaw: null,
            active: false,
            type: 'plugin'
        }, {
            licensedExtension: {
                id: 1,
                variant: 'rent',
                netPrice: 497,
                permissions: []
            }
        }, true);

        expect(wrapper.find('.sw-extension-card-bought__detail-link').props().routerLink).toStrictEqual({ name: 'sw.extension.store.detail', params: { id: '1' } });
    });

    it('should link to the detail page with a store extension', () => {
        wrapper = createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            shortDescription: 'Sample Extension description',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00'
            },
            storeLicense: {
                variants: [{}]
            },
            storeExtension: {
                id: 5
            },
            permissions: {},
            images: [],
            icon: null,
            iconRaw: null,
            active: false,
            type: 'plugin'
        }, {
            licensedExtension: {
                id: 1,
                variant: 'rent',
                netPrice: 497,
                permissions: []
            }
        }, true);

        expect(wrapper.find('.sw-extension-card-bought__detail-link').text()).toBe('sw-extension-store.component.sw-extension-card-base.contextMenu.seeDetailsLabel');
        expect(wrapper.find('.sw-extension-card-bought__detail-link').props().routerLink).toStrictEqual({ name: 'sw.extension.store.detail', params: { id: '5' } });
    });

    it('should open the rating modal', () => {
        expect(wrapper.vm.showRatingModal).toEqual(false);

        expect(wrapper.find('.sw-extension-card-bought__rate-link').text()).toBe('sw-extension-store.component.sw-extension-card-base.contextMenu.rateLabel');
        wrapper.find('.sw-extension-card-bought__rate-link').trigger('click');

        expect(wrapper.vm.showRatingModal).toEqual(true);
    });


    it('should not display the update to button, if there is not a newer version', () => {
        wrapper = createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            shortDescription: 'Sample Extension description',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00'
            },
            storeLicense: {
                variants: [{}]
            },
            storeExtension: {
                id: 5
            },
            permissions: {},
            images: [],
            icon: null,
            iconRaw: null,
            active: false,
            type: 'plugin'
        }, {
            licensedExtension: {
                id: 1,
                variant: 'rent',
                netPrice: 497,
                permissions: []
            }
        }, true);
    });
});
