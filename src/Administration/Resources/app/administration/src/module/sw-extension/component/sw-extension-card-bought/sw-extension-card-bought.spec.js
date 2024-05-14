/* eslint-disable max-len */
import { mount } from '@vue/test-utils';

import ExtensionErrorService from 'src/module/sw-extension/service/extension-error.service';
import ShopwareExtensionService from 'src/module/sw-extension/service/shopware-extension.service';
import ExtensionStoreActionService from 'src/module/sw-extension/service/extension-store-action.service';
import 'src/module/sw-extension/mixin/sw-extension-error.mixin';
import extensionStore from 'src/module/sw-extension/store/extensions.store';

Shopware.Application.addServiceProvider('loginService', () => {
    return {
        getToken: jest.fn(() => Promise.resolve({ access: true, refresh: true })),
    };
});

const httpClient = {
    post: jest.fn(),
    get: jest.fn(),
    delete: jest.fn(),
};

Shopware.Application.getContainer('init').httpClient = httpClient;

const extensionStoreActionService = new ExtensionStoreActionService(
    Shopware.Application.getContainer('init').httpClient,
    Shopware.Service('loginService'),
);

Shopware.Application.addServiceProvider('extensionStoreActionService', () => {
    return extensionStoreActionService;
});

Shopware.Application.addServiceProvider('shopwareExtensionService', () => {
    return new ShopwareExtensionService(
        Shopware.Service('appModulesService'),
        Shopware.Service('extensionStoreActionService'),
        Shopware.Service('shopwareDiscountCampaignService'),
    );
});

// Added service manually because `sw-extension-error` is using it
Shopware.Application.addServiceProvider('extensionErrorService', () => {
    return new ExtensionErrorService({}, {
        title: 'global.default.error',
        message: 'global.notification.unspecifiedSaveErrorMessage',
    });
});


async function createWrapper(extension) {
    return mount(await wrapTestComponent('sw-extension-card-bought', { sync: true }), {
        global: {
            mocks: {
                $tc: (v1, v2, v3) => (v1 || v2 ? v1 : JSON.stringify([v1, v2, v3])),
            },
            mixins: [
                Shopware.Mixin.getByName('sw-extension-error'),
            ],
            stubs: {

                'sw-meteor-card': await wrapTestComponent('sw-meteor-card', { sync: true }),
                'sw-switch-field': await wrapTestComponent('sw-switch-field', { sync: true }),
                'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-context-menu': await wrapTestComponent('sw-context-menu', { sync: true }),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item', { sync: true }),
                'sw-loader': await wrapTestComponent('sw-loader', { sync: true }),
                'sw-icon': await wrapTestComponent('sw-icon', { sync: true }),
                'sw-icon-deprecated': await wrapTestComponent('sw-icon-deprecated', { sync: true }),
                'sw-circle-icon': await wrapTestComponent('sw-circle-icon', { sync: true }),
                'router-link': {
                    template: '<div class="sw-router-link"><slot></slot></div>',
                    props: ['to'],
                },
                'sw-extension-removal-modal': await wrapTestComponent('sw-extension-removal-modal', { sync: true }),
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                          <slot name="modal-header"></slot>
                          <slot></slot>
                          <slot name="modal-footer"></slot>
                        </div>
                    `,
                },
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-extension-adding-failed': await wrapTestComponent('sw-extension-adding-failed', { sync: true }),
                'sw-extension-icon': await wrapTestComponent('sw-extension-icon', { sync: true }),
                'sw-extension-rating-modal': true,
            },
            provide: {
                extensionStoreActionService: Shopware.Service('extensionStoreActionService'),
                shopwareExtensionService: Shopware.Service('shopwareExtensionService'),
                extensionErrorService: Shopware.Service('extensionErrorService'),
                cacheApiService: {},
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
            },
        },
        props: {
            extension,
        },
    });
}

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-extension-card-bought', () => {
    beforeAll(() => {
        Shopware.Context.api.assetsPath = '';
    });

    beforeEach(() => {
        if (Shopware.State.get('shopwareExtensions')) {
            Shopware.State.unregisterModule('shopwareExtensions');
        }
        Shopware.State.registerModule('shopwareExtensions', extensionStore);
    });

    it('should display the extension information', async () => {
        const wrapper = await createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00',
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
                discountInformation: null,
            },
            permissions: {},
            icon: 'https://example.com',
            iconRaw: null,
            active: false,
            type: 'plugin',
        });

        expect(wrapper.find('.sw-extension-card-base__info-name')
            .text()).toBe('Sample Extension Label');
        expect(wrapper.find('.sw-extension-icon img')
            .attributes('src')).toBe('https://example.com');
        expect(wrapper.find('.sw-extension-card-base__meta-info')
            .text().replace(/\s/g, ''))
            .toBe('sw-extension-store.component.sw-extension-card-base.installedLabel01/02/2021');
    });

    it('should display a placeholder icon and the rent price', async () => {
        const wrapper = await createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00',
            },
            storeLicense: {
                variants: [{}],
            },
            permissions: {},
            images: [],
            icon: null,
            iconRaw: null,
            active: false,
            type: 'plugin',
        }, {
            licensedExtension: {
                id: 1,
                variant: 'rent',
                netPrice: 497,
                permissions: [],
            },
        }, true);

        expect(wrapper.find('.sw-extension-card-base__info-name')
            .text()).toBe('Sample Extension Label');
        expect(wrapper.find('.sw-extension-icon img')
            .attributes().src).toBe('administration/static/img/theme/default_theme_preview.jpg');
        expect(wrapper.find('.sw-extension-card-base__meta-info')
            .text().replace(/\s/g, ''))
            .toBe('sw-extension-store.component.sw-extension-card-base.installedLabel01/02/2021');
    });

    it('should link to the detail page', async () => {
        const wrapper = await createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00',
            },
            storeLicense: {
                variants: [{}],
            },
            permissions: {},
            images: [],
            icon: null,
            iconRaw: null,
            active: false,
            type: 'plugin',
        });

        const detailLink = wrapper.getComponent('.sw-extension-card-bought__detail-link');

        expect(detailLink.props('routerLink'))
            .toStrictEqual({ name: 'sw.extension.store.detail', params: { id: '1' } });
    });

    it('should link to the detail page with a store extension', async () => {
        const wrapper = await createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00',
            },
            storeLicense: {
                variants: [{}],
            },
            storeExtension: {
                id: 5,
            },
            permissions: {},
            images: [],
            icon: null,
            iconRaw: null,
            active: false,
            type: 'plugin',
        });

        const detailLink = wrapper.getComponent('.sw-extension-card-bought__detail-link');

        expect(detailLink.text()).toBe('sw-extension-store.component.sw-extension-card-base.contextMenu.seeDetailsLabel');
        expect(detailLink.props('routerLink')).toStrictEqual({ name: 'sw.extension.store.detail', params: { id: '5' } });
    });

    it('should open the rating modal', async () => {
        const wrapper = await createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            languages: [],
            rating: 3,
            numberOfRatings: 10,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00',
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
                discountInformation: null,
            },
            permissions: {},
            icon: 'https://example.com',
            iconRaw: null,
            active: false,
            type: 'plugin',
        });
        expect(wrapper.vm.showRatingModal).toBe(false);

        const rateLink = wrapper.getComponent('.sw-extension-card-bought__rate-link');

        expect(rateLink.text()).toBe('sw-extension-store.component.sw-extension-card-base.contextMenu.rateLabel');
        await rateLink.trigger('click');

        expect(wrapper.vm.showRatingModal).toBe(true);
    });

    it('should not try to cancel the extension subscription on remove attempt when it already has an expiry date', async () => {
        httpClient.delete.mockImplementation(() => {
            return Promise.resolve();
        });

        const cancelLicenceSpy = jest.spyOn(extensionStoreActionService, 'cancelLicense');
        const removeExtensionSpy = jest.spyOn(extensionStoreActionService, 'removeExtension');

        const wrapper = await createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            languages: [],
            rating: null,
            numberOfRatings: 0,
            installedAt: null,
            storeLicense: {
                variants: [{}],
                variant: 'rent',
                // The expiration date is already given before the remove attempt
                expirationDate: '2025-08-01T03:30:35+01:00',
            },
            storeExtension: {
                id: 5,
            },
            permissions: {},
            images: [],
            icon: null,
            iconRaw: null,
            active: false,
            type: 'plugin',
            source: 'local',
        });

        await wrapper.get('.sw-extension-card-base__remove-link').trigger('click');
        expect(wrapper.find('.sw-extension-removal-modal').exists()).toBe(true);

        await wrapper.get('.sw-extension-removal-modal .sw-button--danger').trigger('click');
        expect(wrapper.find('.sw-extension-removal-modal').exists()).toBe(false);
        expect(cancelLicenceSpy).toHaveBeenCalledTimes(0);
        expect(removeExtensionSpy).toHaveBeenCalledTimes(1);
        expect(httpClient.delete).toHaveBeenCalledTimes(1);
    });

    it('should try to cancel the extension subscription on remove attempt when it has no expiry date', async () => {
        httpClient.delete.mockImplementation(() => {
            return Promise.resolve({ data: 'foo' });
        });

        const cancelLicenceSpy = jest.spyOn(extensionStoreActionService, 'cancelLicense');
        const removeExtensionSpy = jest.spyOn(extensionStoreActionService, 'removeExtension');

        const wrapper = await createWrapper({
            id: 1,
            name: 'Sample Extension',
            label: 'Sample Extension Label',
            languages: [],
            rating: null,
            numberOfRatings: 0,
            installedAt: {
                date: '2021-02-01T03:30:35+01:00',
            },
            storeLicense: {
                variants: [{}],
                variant: 'rent',
                expirationDate: null,
                id: '1337',
            },
            storeExtension: {
                id: 5,
            },
            permissions: {},
            images: [],
            icon: null,
            iconRaw: null,
            active: false,
            type: 'plugin',
        });

        await wrapper.get('.sw-extension-card-base__cancel-and-remove-link').trigger('click');
        expect(wrapper.find('.sw-extension-removal-modal').exists()).toBe(true);

        await wrapper.get('.sw-extension-removal-modal .sw-button--danger').trigger('click');
        expect(wrapper.find('.sw-extension-removal-modal').exists()).toBe(false);
        expect(cancelLicenceSpy).toHaveBeenCalledTimes(1);
        expect(removeExtensionSpy).toHaveBeenCalledTimes(1);
        expect(httpClient.delete).toHaveBeenCalledTimes(2);
    });

    it('should display error on install and download attempt when app subscription is expired', async () => {
        httpClient.post.mockImplementation(() => {
            // eslint-disable-next-line prefer-promise-reject-errors
            return Promise.reject({
                response: {
                    data: {
                        errors: [
                            {
                                code: 'FRAMEWORK__STORE_ERROR',
                                detail: 'The download of the extension is not allowed, please purchase a corresponding license or contact the customer service',
                                meta: {
                                    documentationLink: 'https://docs.shopware.com/en/shopware-6-en',
                                },
                                status: '500',
                                title: 'Download not allowed',
                            },
                        ],
                    },
                },
            });
        });

        const wrapper = await createWrapper({
            id: 1,
            name: 'Expired extension',
            label: 'Expired extension Label',
            languages: [],
            rating: null,
            numberOfRatings: 0,
            installedAt: null,
            storeLicense: {
                variants: [{}],
                variant: 'rent',
                expirationDate: '2021-06-08T00:00:00+02:00',
                id: 5552,
            },
            storeExtension: null,
            permissions: {},
            images: [],
            icon: null,
            iconRaw: null,
            active: false,
            source: 'store',
            type: 'app',
        });

        await wrapper.get('.sw-extension-card-base__open-extension').trigger('click');

        expect(wrapper.find('.sw-extension-card-bought__installation-failed-modal').exists()).toBe(true);
        expect(wrapper.vm.installationFailedError).toEqual({
            title: 'Download not allowed',
            message: 'The download of the extension is not allowed, please purchase a corresponding license or contact the customer service',
            details: null,
            parameters: {
                documentationLink: 'https://docs.shopware.com/en/shopware-6-en',
            },
        });

        expect(wrapper.find('.sw-extension-card-bought__installation-failed-modal h3').text()).toBe('Download not allowed');
        expect(wrapper.find('.sw-extension-card-bought__installation-failed-modal h3 + p').text())
            .toBe('The download of the extension is not allowed, please purchase a corresponding license or contact the customer service');
        expect(wrapper.find('.sw-extension-card-bought__installation-failed-modal p > a').text()).toBe('https://docs.shopware.com/en/shopware-6-en');
    });

    describe('test display of rent and trail phase information', () => {
        it.each([{
            testCaseName: 'should display when a rent will expire',
            storeLicense: {
                variant: 'rent',
                expirationDate: '2021-06-08T00:00:00+02:00',
                expired: false,
            },
            expectedTextSnippet: 'sw-extension-store.component.sw-extension-card-bought.rentWillExpireAt',
        }, {
            testCaseName: 'should display when a rent is already expired',
            storeLicense: {
                variant: 'rent',
                expirationDate: '2021-06-08T00:00:00+02:00',
                expired: true,
            },
            expectedTextSnippet: 'sw-extension-store.component.sw-extension-card-bought.rentExpiredAt',
            expectedIcon: 'solid-exclamation-circle',
        }, {
            testCaseName: 'should display when a test phase will expire',
            storeLicense: {
                variant: 'test',
                expirationDate: '2021-06-08T00:00:00+02:00',
                expired: false,
            },
            expectedTextSnippet: 'sw-extension-store.component.sw-extension-card-bought.testPhaseWillExpireAt',
        }, {
            testCaseName: 'should display when a test phase is already expired',
            storeLicense: {
                variant: 'test',
                expirationDate: '2021-06-08T00:00:00+02:00',
                expired: true,
            },
            expectedTextSnippet: 'sw-extension-store.component.sw-extension-card-bought.testPhaseExpiredAt',
            expectedIcon: 'solid-exclamation-circle',
        }])('$testCaseName', async ({ storeLicense, expectedTextSnippet, expectedIcon }) => {
            const wrapper = await createWrapper({
                id: 555,
                name: 'Test extension',
                label: 'Test extension label',
                languages: [],
                rating: null,
                numberOfRatings: 0,
                installedAt: null,
                storeLicense: storeLicense,
                storeExtension: null,
                permissions: {},
                images: [],
                icon: null,
                iconRaw: null,
                active: false,
                source: 'store',
                type: 'app',
            });

            const infoSubscriptionExpiry = wrapper.get('.sw-extension-card-bought__info-subscription-expiry');
            expect(infoSubscriptionExpiry.text()).toBe(expectedTextSnippet);

            const icon = infoSubscriptionExpiry.findComponent('.sw-icon');

            if (expectedIcon) {
                // eslint-disable-next-line jest/no-conditional-expect
                expect(icon.props('name')).toBe(expectedIcon);
            } else {
                // eslint-disable-next-line jest/no-conditional-expect
                expect(icon.exists()).toBe(false);
            }
        });
    });
});
