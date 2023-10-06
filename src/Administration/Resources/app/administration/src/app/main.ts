/**
 * @package admin
 */

/** Initializer */
import initializers from 'src/app/init';
import preInitializer from 'src/app/init-pre/';
import postInitializer from 'src/app/init-post/';

/** View Adapter */
import VueAdapter from 'src/app/adapter/view/vue.adapter';

/** Services */
import FeatureService from 'src/app/service/feature.service';
import MenuService from 'src/app/service/menu.service';
import PrivilegesService from 'src/app/service/privileges.service';
import AclService from 'src/app/service/acl.service';
import LoginService from 'src/core/service/login.service';
import EntityMappingService from 'src/core/service/entity-mapping.service';
import JsonApiParser from 'src/core/service/jsonapi-parser.service';
import ValidationService from 'src/core/service/validation.service';
import TimezoneService from 'src/core/service/timezone.service';
import RuleConditionService from 'src/app/service/rule-condition.service';
import ProductStreamConditionService from 'src/app/service/product-stream-condition.service';
import StateStyleService from 'src/app/service/state-style.service';
import CustomFieldService from 'src/app/service/custom-field.service';
import ExtensionHelperService from 'src/app/service/extension-helper.service';
import LanguageAutoFetchingService from 'src/app/service/language-auto-fetching.service';
import SearchTypeService from 'src/app/service/search-type.service';
import LicenseViolationsService from 'src/app/service/license-violations.service';
import ShortcutService from 'src/app/service/shortcut.service';
import LocaleToLanguageService from 'src/app/service/locale-to-language.service';
import addPluginUpdatesListener from 'src/core/service/plugin-updates-listener.service';
import addShopwareUpdatesListener from 'src/core/service/shopware-updates-listener.service';
import addCustomerGroupRegistrationListener from 'src/core/service/customer-group-registration-listener.service';
import LocaleHelperService from 'src/app/service/locale-helper.service';
import FilterService from 'src/app/service/filter.service';
import AppCmsService from 'src/app/service/app-cms.service';
import MediaDefaultFolderService from 'src/app/service/media-default-folder.service';
import AppAclService from 'src/app/service/app-acl.service';
import ShopwareDiscountCampaignService from 'src/app/service/discount-campaign.service';
import SearchRankingService from 'src/app/service/search-ranking.service';
import SearchPreferencesService from 'src/app/service/search-preferences.service';
import RecentlySearchService from 'src/app/service/recently-search.service';
import UserActivityService from 'src/app/service/user-activity.service';
import EntityValidationService from 'src/app/service/entity-validation.service';
import CustomEntityDefinitionService from 'src/app/service/custom-entity-definition.service';

/** Import Feature */
import Feature from 'src/core/feature';

/** Import decorators */
import 'src/app/decorator';

/** Import global styles */
import 'src/app/assets/scss/all.scss';

import ChangesetGenerator from '../core/data/changeset-generator.data';
import ErrorResolver from '../core/data/error-resolver.data';

/** Application Bootstrapper */
const { Application } = Shopware;

const factoryContainer = Application.getContainer('factory');

/** Create View Adapter */
const adapter = new VueAdapter(Application);

Application.setViewAdapter(adapter);

// Merge all initializer
const allInitializers = { ...preInitializer, ...initializers, ...postInitializer };

// Add initializers to application
Object.keys(allInitializers).forEach((key) => {
    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const initializer = allInitializers[key];
    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
    Application.addInitializer(key, initializer);
});

// Add service providers
Application
    .addServiceProvider('feature', () => {
        return new FeatureService(Feature);
    })
    .addServiceProvider('customEntityDefinitionService', () => {
        return new CustomEntityDefinitionService();
    })
    .addServiceProvider('menuService', () => {
        return new MenuService(factoryContainer.module);
    })
    .addServiceProvider('privileges', () => {
        return new PrivilegesService();
    })
    .addServiceProvider('acl', () => {
        return new AclService(Shopware.State);
    })
    .addServiceProvider('loginService', () => {
        const serviceContainer = Application.getContainer('service');
        const initContainer = Application.getContainer('init');

        const loginService = LoginService(initContainer.httpClient, Shopware.Context.api);

        addPluginUpdatesListener(loginService, serviceContainer);
        addShopwareUpdatesListener(loginService, serviceContainer);
        addCustomerGroupRegistrationListener(loginService);

        return loginService;
    })
    .addServiceProvider('jsonApiParserService', () => {
        return JsonApiParser;
    })
    .addServiceProvider('validationService', () => {
        return ValidationService;
    })
    .addServiceProvider('entityValidationService', () => {
        return new EntityValidationService(
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            Application.getContainer('factory').entityDefinition,
            new ChangesetGenerator(),
            new ErrorResolver(),
        );
    })
    .addServiceProvider('timezoneService', () => {
        return new TimezoneService();
    })
    .addServiceProvider('ruleConditionDataProviderService', () => {
        return new RuleConditionService();
    })
    .addServiceProvider('productStreamConditionService', () => {
        return new ProductStreamConditionService();
    })
    .addServiceProvider('customFieldDataProviderService', () => {
        return new CustomFieldService();
    })
    .addServiceProvider('extensionHelperService', () => {
        return new ExtensionHelperService({
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            extensionStoreActionService: Shopware.Service('extensionStoreActionService'),
        });
    })
    .addServiceProvider('languageAutoFetchingService', () => {
        return LanguageAutoFetchingService();
    })
    .addServiceProvider('stateStyleDataProviderService', () => {
        return new StateStyleService();
    })
    .addServiceProvider('searchTypeService', () => {
        return new SearchTypeService();
    })
    .addServiceProvider('localeToLanguageService', () => {
        return LocaleToLanguageService();
    })
    .addServiceProvider('entityMappingService', () => {
        return EntityMappingService;
    })
    .addServiceProvider('shortcutService', () => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        return new ShortcutService(factoryContainer.shortcut);
    })
    .addServiceProvider('licenseViolationService', () => {
        return LicenseViolationsService(Application.getContainer('service').storeService);
    })
    .addServiceProvider('localeHelper', () => {
        return new LocaleHelperService({
            Shopware: Shopware,
            localeRepository: Shopware.Service('repositoryFactory').create('locale'),
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            snippetService: Shopware.Service('snippetService'),
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            localeFactory: Application.getContainer('factory').locale,
        });
    })
    .addServiceProvider('filterService', () => {
        return new FilterService({
            userConfigRepository: Shopware.Service('repositoryFactory').create('user_config'),
        });
    })
    .addServiceProvider('mediaDefaultFolderService', () => {
        return MediaDefaultFolderService();
    })
    .addServiceProvider('appAclService', () => {
        return new AppAclService({
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            privileges: Shopware.Service('privileges'),
            appRepository: Shopware.Service('repositoryFactory').create('app'),
        });
    })
    .addServiceProvider('appCmsService', (container: $TSFixMe) => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
        const appCmsBlocksService = container.appCmsBlocks;
        return new AppCmsService(appCmsBlocksService, adapter);
    })
    .addServiceProvider('shopwareDiscountCampaignService', () => {
        return new ShopwareDiscountCampaignService();
    })
    .addServiceProvider('searchRankingService', () => {
        return new SearchRankingService();
    })
    .addServiceProvider('recentlySearchService', () => {
        return new RecentlySearchService();
    })
    .addServiceProvider('searchPreferencesService', () => {
        return new SearchPreferencesService({
            userConfigRepository: Shopware.Service('repositoryFactory').create('user_config'),
        });
    })
    .addServiceProvider('userActivityService', () => {
        return new UserActivityService();
    });

