import ExtensionStoreActionService from './extension-store-action.service';
import ExtensionStoreDataService from './extension-store-data.service';
import ExtensionLicenseService from './extension-store-licenses.service';
import ShopwareExtensionService from './shopware-extension.service';
import ShopwareDiscountCampaignService from './discount-campaign.service';
import ExtensionApiService from './extension.api.service';
import ExtensionErrorService from './extension-error.service';

const { Application } = Shopware;

Application.addServiceProvider('extensionApiService', () => {
    return new ExtensionApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

Application.addServiceProvider('extensionStoreActionService', () => {
    return new ExtensionStoreActionService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

Application.addServiceProvider('extensionStoreDataService', () => {
    return new ExtensionStoreDataService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

Application.addServiceProvider('extensionStoreLicensesService', () => {
    return new ExtensionLicenseService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

Application.addServiceProvider('shopwareDiscountCampaignService', () => {
    return new ShopwareDiscountCampaignService();
});

Application.addServiceProvider('shopwareExtensionService', () => {
    return new ShopwareExtensionService(
        Shopware.Service('appModulesService'),
        Shopware.Service('extensionStoreActionService'),
        Shopware.Service('extensionStoreLicensesService'),
        Shopware.Service('shopwareDiscountCampaignService')
    );
});

Application.addServiceProvider('extensionErrorService', () => {
    return new ExtensionErrorService({}, {
        title: 'global.default.error',
        message: 'global.notification.unspecifiedSaveErrorMessage'
    });
});
