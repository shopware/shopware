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

/** Import Feature */
import Feature from 'src/core/feature';

/** Import decorators */
import 'src/app/decorator';

/** Import global styles */
import 'src/app/assets/scss/all.scss';

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
    const initializer = allInitializers[key];
    Application.addInitializer(key, initializer);
});

// Add service providers
Application
    .addServiceProvider('feature', () => {
        return new FeatureService(Feature);
    })
    .addServiceProvider('menuService', () => {
        return MenuService(factoryContainer.module);
    })
    .addServiceProvider('privileges', () => {
        return new PrivilegesService();
    })
    .addServiceProvider('acl', () => {
        return new AclService(Shopware.State, Shopware.State.get('settingsItems'));
    })
    .addServiceProvider('loginService', () => {
        const serviceContainer = Application.getContainer('service');
        const initContainer = Application.getContainer('init');

        const loginService = LoginService(initContainer.httpClient, Shopware.Context.api);

        addPluginUpdatesListener(loginService, serviceContainer);
        addShopwareUpdatesListener(loginService, serviceContainer);
        addCustomerGroupRegistrationListener(loginService, serviceContainer);

        return loginService;
    })
    .addServiceProvider('jsonApiParserService', () => {
        return JsonApiParser;
    })
    .addServiceProvider('validationService', () => {
        return ValidationService;
    })
    .addServiceProvider('ruleConditionDataProviderService', () => {
        return RuleConditionService();
    })
    .addServiceProvider('productStreamConditionService', () => {
        return ProductStreamConditionService();
    })
    .addServiceProvider('customFieldDataProviderService', () => {
        return CustomFieldService();
    })
    .addServiceProvider('extensionHelperService', () => {
        return new ExtensionHelperService({
            storeService: Shopware.Service('storeService'),
            extensionStoreActionService: Shopware.Service('extensionStoreActionService'),
        });
    })
    .addServiceProvider('languageAutoFetchingService', () => {
        return LanguageAutoFetchingService();
    })
    .addServiceProvider('stateStyleDataProviderService', () => {
        return StateStyleService();
    })
    .addServiceProvider('searchTypeService', () => {
        return SearchTypeService();
    })
    .addServiceProvider('localeToLanguageService', () => {
        return LocaleToLanguageService();
    })
    .addServiceProvider('entityMappingService', () => {
        return EntityMappingService;
    })
    .addServiceProvider('shortcutService', () => {
        return ShortcutService(factoryContainer.shortcut);
    })
    .addServiceProvider('licenseViolationService', () => {
        return LicenseViolationsService(Application.getContainer('service').storeService);
    })
    .addServiceProvider('localeHelper', () => {
        return new LocaleHelperService({
            Shopware: Shopware,
            localeRepository: Shopware.Service('repositoryFactory').create('locale'),
            snippetService: Shopware.Service('snippetService'),
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
            privileges: Shopware.Service('privileges'),
            appRepository: Shopware.Service('repositoryFactory').create('app'),
        });
    })
    .addServiceProvider('appCmsService', (container) => {
        const appCmsBlocksService = container.appCmsBlocks;
        return new AppCmsService(appCmsBlocksService, adapter);
    });
