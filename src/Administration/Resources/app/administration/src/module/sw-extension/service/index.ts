import type { SubContainer } from 'src/global.types';

import ExtensionStoreActionService from './extension-store-action.service';
import ShopwareExtensionService from './shopware-extension.service';
import ExtensionErrorService from './extension-error.service';

const { Application } = Shopware;

/**
 * @package merchant-services
 */
declare global {
    interface ServiceContainer extends SubContainer<'service'>{
        extensionStoreActionService: ExtensionStoreActionService,
        shopwareExtensionService: ShopwareExtensionService,
        extensionErrorService: ExtensionErrorService,
    }
}

Application.addServiceProvider('extensionStoreActionService', () => {
    return new ExtensionStoreActionService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

Application.addServiceProvider('shopwareExtensionService', () => {
    return new ShopwareExtensionService(
        Shopware.Service('appModulesService'),
        Shopware.Service('extensionStoreActionService'),
        Shopware.Service('shopwareDiscountCampaignService'),
        Shopware.Service('storeService'),
    );
});

Application.addServiceProvider('extensionErrorService', () => {
    const root = Shopware.Application.getApplicationRoot() as Vue;

    return new ExtensionErrorService({
        FRAMEWORK__APP_LICENSE_COULD_NOT_BE_VERIFIED: {
            title: 'sw-extension.errors.appLicenseCouldNotBeVerified.title',
            message: 'sw-extension.errors.appLicenseCouldNotBeVerified.message',
            autoClose: false,
            actions: [
                {
                    label: root.$tc('sw-extension.errors.appLicenseCouldNotBeVerified.actionSetLicenseDomain'),
                    method: () => {
                        void root.$router.push({
                            name: 'sw.settings.store.index',
                        });
                    },
                },
                {
                    label: root.$tc('sw-extension.errors.appLicenseCouldNotBeVerified.actionLogin'),
                    method: () => {
                        void root.$router.push({
                            name: 'sw.extension.my-extensions.account',
                        });
                    },
                },
            ],
        },
        FRAMEWORK__APP_NOT_COMPATIBLE: {
            title: 'global.default.error',
            message: 'sw-extension.errors.appIsNotCompatible',
        },
    }, {
        title: 'global.default.error',
        message: 'global.notification.unspecifiedSaveErrorMessage',
    });
});
