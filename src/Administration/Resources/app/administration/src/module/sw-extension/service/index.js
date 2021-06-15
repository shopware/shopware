import ExtensionStoreActionService from './extension-store-action.service';
import ShopwareExtensionService from './shopware-extension.service';
import ShopwareDiscountCampaignService from './discount-campaign.service';
import ExtensionErrorService from './extension-error.service';

const { Application } = Shopware;


Application.addServiceProvider('extensionStoreActionService', () => {
    return new ExtensionStoreActionService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

Application.addServiceProvider('shopwareDiscountCampaignService', () => {
    return new ShopwareDiscountCampaignService();
});

Application.addServiceProvider('shopwareExtensionService', () => {
    return new ShopwareExtensionService(
        Shopware.Service('appModulesService'),
        Shopware.Service('extensionStoreActionService'),
        Shopware.Service('shopwareDiscountCampaignService'),
    );
});

Application.addServiceProvider('extensionErrorService', () => {
    return new ExtensionErrorService({}, {
        title: 'global.default.error',
        message: 'global.notification.unspecifiedSaveErrorMessage',
    });
});
