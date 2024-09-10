/**
 * @package admin
 */
import initializeApiServices from 'src/app/init-pre/api-services.init';

describe('src/app/init-pre/api-services.init.ts', () => {
    /**
     * [
     *         'aclApiService',
     *         'appActionButtonService',
     *         'appCmsBlocks',
     *         'appModulesService',
     *         'appUrlChangeService',
     *         'businessEventService',
     *         'cacheApiService',
     *         'calculate-price',
     *         'cartStoreService',
     *         'checkoutStoreService',
     *         'configService',
     *         'customSnippetApiService',
     *         'customerGroupRegistrationService',
     *         'customerValidationService',
     *         'documentService',
     *         'excludedSearchTermService',
     *         'extensionSdkService',
     *         'firstRunWizardService',
     *         'flowActionService',
     *         'importExportService',
     *         'integrationService',
     *         'knownIpsService',
     *         'languagePluginService',
     *         'mailService',
     *         'mediaFolderService',
     *         'mediaService',
     *         'messageQueueService',
     *         'notificationsService',
     *         'numberRangeService',
     *         'orderDocumentApiService',
     *         'orderStateMachineService',
     *         'orderService',
     *         'productExportService',
     *         'productStreamPreviewService',
     *         'promotionSyncService',
     *         'recommendationsService',
     *         'ruleConditionsConfigApiService',
     *         'salesChannelService',
     *         'scheduledTaskService',
     *         'searchService',
     *         'seoUrlTemplateService',
     *         'seoUrlService',
     *         'snippetSetService',
     *         'snippetService',
     *         'stateMachineService',
     *         'contextStoreService',
     *         'storeService',
     *         'syncService',
     *         'systemConfigApiService',
     *         'tagApiService',
     *         'updateService',
     *         'userActivityApiService',
     *         'userConfigService',
     *         'userInputSanitizeService',
     *         'userRecoveryService',
     *         'userValidationService',
     *         'userService'
     *       ]
     */

    it('should initialize the api services', () => {
        expect(Shopware.Service('aclApiService')).toBeUndefined();
        expect(Shopware.Service('appActionButtonService')).toBeUndefined();
        expect(Shopware.Service('appCmsBlocks')).toBeUndefined();
        expect(Shopware.Service('appModulesService')).toBeUndefined();
        expect(Shopware.Service('appUrlChangeService')).toBeUndefined();
        expect(Shopware.Service('businessEventService')).toBeUndefined();
        expect(Shopware.Service('cacheApiService')).toBeUndefined();
        expect(Shopware.Service('calculate-price')).toBeUndefined();
        expect(Shopware.Service('cartStoreService')).toBeUndefined();
        expect(Shopware.Service('checkoutStoreService')).toBeUndefined();
        expect(Shopware.Service('configService')).toBeUndefined();
        expect(Shopware.Service('customSnippetApiService')).toBeUndefined();
        expect(Shopware.Service('customerGroupRegistrationService')).toBeUndefined();
        expect(Shopware.Service('customerValidationService')).toBeUndefined();
        expect(Shopware.Service('documentService')).toBeUndefined();
        expect(Shopware.Service('excludedSearchTermService')).toBeUndefined();
        expect(Shopware.Service('extensionSdkService')).toBeUndefined();
        expect(Shopware.Service('firstRunWizardService')).toBeUndefined();
        expect(Shopware.Service('flowActionService')).toBeUndefined();
        expect(Shopware.Service('importExportService')).toBeUndefined();
        expect(Shopware.Service('integrationService')).toBeUndefined();
        expect(Shopware.Service('knownIpsService')).toBeUndefined();
        expect(Shopware.Service('languagePluginService')).toBeUndefined();
        expect(Shopware.Service('mailService')).toBeUndefined();
        expect(Shopware.Service('mediaFolderService')).toBeUndefined();
        expect(Shopware.Service('mediaService')).toBeUndefined();
        expect(Shopware.Service('messageQueueService')).toBeUndefined();
        expect(Shopware.Service('notificationsService')).toBeUndefined();
        expect(Shopware.Service('numberRangeService')).toBeUndefined();
        expect(Shopware.Service('orderDocumentApiService')).toBeUndefined();
        expect(Shopware.Service('orderStateMachineService')).toBeUndefined();
        expect(Shopware.Service('orderService')).toBeUndefined();
        expect(Shopware.Service('productExportService')).toBeUndefined();
        expect(Shopware.Service('productStreamPreviewService')).toBeUndefined();
        expect(Shopware.Service('promotionSyncService')).toBeUndefined();
        expect(Shopware.Service('recommendationsService')).toBeUndefined();
        expect(Shopware.Service('ruleConditionsConfigApiService')).toBeUndefined();
        expect(Shopware.Service('salesChannelService')).toBeUndefined();
        expect(Shopware.Service('scheduledTaskService')).toBeUndefined();
        expect(Shopware.Service('searchService')).toBeUndefined();
        expect(Shopware.Service('seoUrlTemplateService')).toBeUndefined();
        expect(Shopware.Service('seoUrlService')).toBeUndefined();
        expect(Shopware.Service('snippetSetService')).toBeUndefined();
        expect(Shopware.Service('snippetService')).toBeUndefined();
        expect(Shopware.Service('stateMachineService')).toBeUndefined();
        expect(Shopware.Service('contextStoreService')).toBeUndefined();
        expect(Shopware.Service('storeService')).toBeUndefined();
        expect(Shopware.Service('syncService')).toBeUndefined();
        expect(Shopware.Service('systemConfigApiService')).toBeUndefined();
        expect(Shopware.Service('tagApiService')).toBeUndefined();
        expect(Shopware.Service('updateService')).toBeUndefined();
        expect(Shopware.Service('userActivityApiService')).toBeUndefined();
        expect(Shopware.Service('userConfigService')).toBeUndefined();
        expect(Shopware.Service('userInputSanitizeService')).toBeUndefined();
        expect(Shopware.Service('userRecoveryService')).toBeUndefined();
        expect(Shopware.Service('userValidationService')).toBeUndefined();
        expect(Shopware.Service('userService')).toBeUndefined();

        initializeApiServices();

        expect(Shopware.Service('aclApiService')).toBeDefined();
        expect(Shopware.Service('appActionButtonService')).toBeDefined();
        expect(Shopware.Service('appCmsBlocks')).toBeDefined();
        expect(Shopware.Service('appModulesService')).toBeDefined();
        expect(Shopware.Service('appUrlChangeService')).toBeDefined();
        expect(Shopware.Service('businessEventService')).toBeDefined();
        expect(Shopware.Service('cacheApiService')).toBeDefined();
        expect(Shopware.Service('calculate-price')).toBeDefined();
        expect(Shopware.Service('cartStoreService')).toBeDefined();
        expect(Shopware.Service('checkoutStoreService')).toBeDefined();
        expect(Shopware.Service('configService')).toBeDefined();
        expect(Shopware.Service('customSnippetApiService')).toBeDefined();
        expect(Shopware.Service('customerGroupRegistrationService')).toBeDefined();
        expect(Shopware.Service('customerValidationService')).toBeDefined();
        expect(Shopware.Service('documentService')).toBeDefined();
        expect(Shopware.Service('excludedSearchTermService')).toBeDefined();
        expect(Shopware.Service('extensionSdkService')).toBeDefined();
        expect(Shopware.Service('firstRunWizardService')).toBeDefined();
        expect(Shopware.Service('flowActionService')).toBeDefined();
        expect(Shopware.Service('importExportService')).toBeDefined();
        expect(Shopware.Service('integrationService')).toBeDefined();
        expect(Shopware.Service('knownIpsService')).toBeDefined();
        expect(Shopware.Service('languagePluginService')).toBeDefined();
        expect(Shopware.Service('mailService')).toBeDefined();
        expect(Shopware.Service('mediaFolderService')).toBeDefined();
        expect(Shopware.Service('mediaService')).toBeDefined();
        expect(Shopware.Service('messageQueueService')).toBeDefined();
        expect(Shopware.Service('notificationsService')).toBeDefined();
        expect(Shopware.Service('numberRangeService')).toBeDefined();
        expect(Shopware.Service('orderDocumentApiService')).toBeDefined();
        expect(Shopware.Service('orderStateMachineService')).toBeDefined();
        expect(Shopware.Service('orderService')).toBeDefined();
        expect(Shopware.Service('productExportService')).toBeDefined();
        expect(Shopware.Service('productStreamPreviewService')).toBeDefined();
        expect(Shopware.Service('promotionSyncService')).toBeDefined();
        expect(Shopware.Service('recommendationsService')).toBeDefined();
        expect(Shopware.Service('ruleConditionsConfigApiService')).toBeDefined();
        expect(Shopware.Service('salesChannelService')).toBeDefined();
        expect(Shopware.Service('scheduledTaskService')).toBeDefined();
        expect(Shopware.Service('searchService')).toBeDefined();
        expect(Shopware.Service('seoUrlTemplateService')).toBeDefined();
        expect(Shopware.Service('seoUrlService')).toBeDefined();
        expect(Shopware.Service('snippetSetService')).toBeDefined();
        expect(Shopware.Service('snippetService')).toBeDefined();
        expect(Shopware.Service('stateMachineService')).toBeDefined();
        expect(Shopware.Service('contextStoreService')).toBeDefined();
        expect(Shopware.Service('storeService')).toBeDefined();
        expect(Shopware.Service('syncService')).toBeDefined();
        expect(Shopware.Service('systemConfigApiService')).toBeDefined();
        expect(Shopware.Service('tagApiService')).toBeDefined();
        expect(Shopware.Service('updateService')).toBeDefined();
        expect(Shopware.Service('userActivityApiService')).toBeDefined();
        expect(Shopware.Service('userConfigService')).toBeDefined();
        expect(Shopware.Service('userInputSanitizeService')).toBeDefined();
        expect(Shopware.Service('userRecoveryService')).toBeDefined();
        expect(Shopware.Service('userValidationService')).toBeDefined();
        expect(Shopware.Service('userService')).toBeDefined();
    });
});
