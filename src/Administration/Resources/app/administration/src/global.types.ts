/**
 * @package admin
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable import/no-named-default */
import type { default as Bottle, Decorator } from 'bottlejs';
import type { Route } from 'vue-router';
import type VueRouter from 'vue-router';
import type FeatureService from 'src/app/service/feature.service';
import type { LoginService } from 'src/core/service/login.service';
import type { ContextState } from 'src/app/state/context.store';
import type { ExtensionComponentSectionsState } from 'src/app/state/extension-component-sections.store';
import type { AxiosInstance } from 'axios';
import type { ShopwareClass } from 'src/core/shopware';
import type { ModuleTypes } from 'src/core/factory/module.factory';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type { default as VueType } from 'vue';
import type ExtensionSdkService from 'src/core/service/api/extension-sdk.service';
import type CartStoreService from 'src/core/service/api/cart-store-api.api.service';
import type CustomSnippetApiService from 'src/core/service/api/custom-snippet.api.service';
import type LocaleFactory from 'src/core/factory/locale.factory';
import type UserActivityService from 'src/app/service/user-activity.service';
import type { FullState } from 'src/core/factory/state.factory';
import type ModuleFactory from 'src/core/factory/module.factory';
import type DirectiveFactory from 'src/core/factory/directive.factory';
import type EntityDefinitionFactory from 'src/core/factory/entity-definition.factory';
import type FilterFactoryData from 'src/core/data/filter-factory.data';
import type UserApiService from 'src/core/service/api/user.api.service';
import type ApiServiceFactory from 'src/core/factory/api-service.factory';
import type { ExtensionsState } from './app/state/extensions.store';
import type { ComponentConfig } from './core/factory/async-component.factory';
import type { TabsState } from './app/state/tabs.store';
import type { MenuItemState } from './app/state/menu-item.store';
import type { ModalsState } from './app/state/modals.store';
import type { ExtensionSdkModuleState } from './app/state/extension-sdk-module.store';
import type { MainModuleState } from './app/state/main-module.store';
import type { ActionButtonState } from './app/state/action-button.store';
import type StoreApiService from './core/service/api/store.api.service';
import type ShopwareDiscountCampaignService from './app/service/discount-campaign.service';
import type AppModulesService from './core/service/api/app-modules.service';
import type { ShopwareExtensionsState } from './module/sw-extension/store/extensions.store';
import type { PaymentOverviewCardState } from './module/sw-settings-payment/state/overview-cards.store';
import type { SwOrderState } from './module/sw-order/state/order.store';
import type AclService from './app/service/acl.service';
import type { ShopwareAppsState } from './app/state/shopware-apps.store';
import type EntityValidationService from './app/service/entity-validation.service';
import type CustomEntityDefinitionService from './app/service/custom-entity-definition.service';
import type CmsPageTypeService from './module/sw-cms/service/cms-page-type.service';
import type { SdkLocationState } from './app/state/sdk-location.store';
import type StoreContextService from './core/service/api/store-context.api.service';
import type OrderStateMachineApiService from './core/service/api/order-state-machine.api.service';
import type cmsElementFavoritesService from './module/sw-cms/service/cms-element-favorites.service';
import type cmsBlockFavoritesService from './module/sw-cms/service/cms-block-favorites.service';
import type CheckoutStoreService from './core/service/api/checkout-store.api.service';
import type ExtensionHelperService from './app/service/extension-helper.service';
import type AsyncComponentFactory from './core/factory/async-component.factory';
import type FilterFactory from './core/factory/filter.factory';
import type StateStyleService from './app/service/state-style.service';
import type RuleConditionService from './app/service/rule-condition.service';
import type SystemConfigApiService from './core/service/api/system-config.api.service';
import type MetricsApiService from './core/service/api/metrics.api.service';
import type ConfigApiService from './core/service/api/config.api.service';
import type ImportExportService from './module/sw-import-export/service/importExport.service';
import type WorkerNotificationFactory from './core/factory/worker-notification.factory';

// trick to make it an "external module" to support global type extension

// base methods for subContainer
// Export for modules and plugins to extend the service definitions
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface SubContainer<ContainerName extends string> {
    $decorator(name: string | Decorator, func?: Decorator): this;
    $register(Obj: Bottle.IRegisterableObject): this;
    $list(): (keyof Bottle.IContainer[ContainerName])[];
}

type SalutationFilterEntityType = {
    salutation: {
        id: string,
        salutationKey: string,
        displayName: string
    },
    title: string,
    firstName: string,
    lastName: string,
    [key: string]: unknown
};

type CmsService = {
    registerCmsElement: (config: { [key: string]: unknown }) => void,
    registerCmsBlock: $TSFixMeFunction,
    getCmsElementConfigByName: $TSFixMeFunction,
    getCmsBlockConfigByName: $TSFixMeFunction,
    getCmsElementRegistry: $TSFixMeFunction,
    getCmsBlockRegistry: $TSFixMeFunction,
    getEntityMappingTypes: $TSFixMeFunction,
    getPropertyByMappingPath: $TSFixMeFunction,
};

// declare global types
declare global {
    /**
     * "any" type which looks more awful in the code so that spot easier
     * the places where we need to fix the TS types
     */
    type $TSFixMe = any;
    type $TSFixMeFunction = (...args: any[]) => any;

    /**
     * Dangerous "unknown" types which are specific enough but do not provide type safety.
     * You should avoid using these.
     */
    type $TSDangerUnknownObject = {[key: string|symbol]: unknown};

    /**
     * Make the Shopware object globally available
     */
    const Shopware: ShopwareClass;
    interface Window { Shopware: ShopwareClass; }

    const _features_: {
        [featureName: string]: boolean
    };

    /**
     * Define global container for the bottle.js containers
     */
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface ServiceContainer extends SubContainer<'service'>{
        loginService: LoginService,
        feature: FeatureService,
        menuService: $TSFixMe,
        privileges: $TSFixMe,
        customEntityDefinitionService: CustomEntityDefinitionService,
        cmsPageTypeService: CmsPageTypeService,
        acl: AclService,
        jsonApiParserService: $TSFixMe,
        validationService: $TSFixMe,
        entityValidationService: EntityValidationService,
        timezoneService: $TSFixMe,
        ruleConditionDataProviderService: RuleConditionService,
        productStreamConditionService: $TSFixMe,
        customFieldDataProviderService: $TSFixMe,
        extensionHelperService: ExtensionHelperService,
        languageAutoFetchingService: $TSFixMe,
        stateStyleDataProviderService: StateStyleService,
        searchTypeService: $TSFixMe,
        localeToLanguageService: $TSFixMe,
        entityMappingService: $TSFixMe,
        shortcutService: $TSFixMe,
        licenseViolationService: $TSFixMe,
        localeHelper: $TSFixMe,
        filterService: $TSFixMe,
        mediaDefaultFolderService: $TSFixMe,
        appAclService: $TSFixMe,
        appCmsService: $TSFixMe,
        entityHydrator: $TSFixMe,
        entityFactory: $TSFixMe,
        userService: UserApiService,
        shopwareDiscountCampaignService: ShopwareDiscountCampaignService,
        cmsService: CmsService,
        cmsElementFavorites: cmsElementFavoritesService,
        cmsBlockFavorites: cmsBlockFavoritesService,
        searchRankingService: $TSFixMe,
        searchPreferencesService: $TSFixMe,
        storeService: StoreApiService,
        contextStoreService: StoreContextService,
        checkoutStoreService: CheckoutStoreService,
        orderStateMachineService: OrderStateMachineApiService,
        repositoryFactory: RepositoryFactory,
        snippetService: $TSFixMe,
        recentlySearchService: $TSFixMe,
        extensionSdkService: ExtensionSdkService,
        appModulesService: AppModulesService,
        cartStoreService: CartStoreService,
        customSnippetApiService: CustomSnippetApiService,
        userActivityService: UserActivityService,
        filterFactory: FilterFactoryData,
        systemConfigApiService: SystemConfigApiService,
        metricsService: MetricsApiService,
        configService: ConfigApiService,
        importExport: ImportExportService,
    }
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface InitContainer extends SubContainer<'init'>{
        state: $TSFixMe,
        router: $TSFixMe,
        httpClient: AxiosInstance,
    }
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface FactoryContainer extends SubContainer<'factory'>{
        component: typeof AsyncComponentFactory,
        template: $TSFixMe,
        module: typeof ModuleFactory,
        entity: $TSFixMe,
        state: () => FullState,
        serviceFactory: $TSFixMe,
        classesFactory: $TSFixMe,
        mixin: $TSFixMe,
        directive: typeof DirectiveFactory,
        filter: typeof FilterFactory,
        locale: typeof LocaleFactory,
        shortcut: $TSFixMe,
        plugin: $TSFixMe,
        apiService: typeof ApiServiceFactory,
        entityDefinition: typeof EntityDefinitionFactory,
        workerNotification: typeof WorkerNotificationFactory,
    }

    interface FilterTypes {
        asset: (value: string) => string,
        currency: $TSFixMeFunction,
        date: (value: string, options?: Intl.DateTimeFormatOptions) => string,
        'file-size': $TSFixMeFunction,
        'media-name': $TSFixMeFunction,
        salutation: (entity: SalutationFilterEntityType, fallbackSnippet: string) => string,
        'stock-color-variant': $TSFixMeFunction
        striphtml: (value: string) => string,
        'thumbnail-size': $TSFixMeFunction,
        truncate: $TSFixMeFunction,
        'unicode-uri': $TSFixMeFunction,
        [key: string]: ((...args: any[]) => any)|undefined,
    }

    /**
     * Define global state for the Vuex store
     */
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface VuexRootState {
        context: ContextState,
        extensions: ExtensionsState,
        tabs: TabsState,
        extensionComponentSections: ExtensionComponentSectionsState,
        paymentOverviewCardState: PaymentOverviewCardState,
        swOrder: SwOrderState,
        session: {
            currentUser: EntitySchema.Entities['user'],
            userPending: boolean,
            languageId: string,
            currentLocale: string|null,
        },
        swCategoryDetail: $TSFixMe,
        menuItem: MenuItemState,
        extensionSdkModules: ExtensionSdkModuleState,
        extensionMainModules: MainModuleState,
        modals: ModalsState,
        actionButtons: ActionButtonState,
        shopwareExtensions: ShopwareExtensionsState,
        extensionEntryRoutes: $TSFixMe,
        shopwareApps: ShopwareAppsState,
        sdkLocation: SdkLocationState,
    }

    /**
     * define global Component
     */
    type VueComponent = ComponentConfig;

    type apiContext = ContextState['api'];

    type appContext = ContextState['app'];

    /**
     * @see Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory
     */
    interface ShopwareHttpError {
        code: string,
        status: string,
        title: string,
        detail: string,
        meta?: {
            file: string,
            line: string,
            trace?: { [key: string]: string },
            parameters?: object,
            previous?: ShopwareHttpError,
        },
        trace?: { [key: string]: string },
    }

    interface StoreApiException extends ShopwareHttpError {
        meta?: ShopwareHttpError['meta'] & {
            documentationLink?: string,
        }
    }

    const flushPromises: () => Promise<void>;
}

/**
 * Link global bottle.js container to the bottle.js container interface
 */
declare module 'bottlejs' { // Use the same module name as the import string
    type IContainerChildren = 'factory' | 'service' | 'init';

    interface IContainer {
        factory: FactoryContainer,
        service: ServiceContainer,
        init: InitContainer,
    }
}

/**
 * Extend the vue-router route information
 */
declare module 'vue-router' {
    interface RouteConfig {
        name: string,
        coreRoute: boolean,
        type: ModuleTypes,
        flag: string,
        isChildren: boolean,
        routeKey: string,
        children: RouteConfig[],
        path: string,
        meta: {
            parentPath?: string,
        }
    }
}

/**
 * Extend this context of vue components with service container types (from inject)
 * and plugins
 */
declare module 'vue/types/vue' {
    interface Vue extends ServiceContainer {
        $createTitle: (identifier?: string|null) => string,
        $router: VueRouter,
        $route: Route,
    }
}

declare module 'vue/types/options' {
    interface ComponentOptions<V extends VueType> {
        shortcuts?: {
            [key: string]: string | {
                active: boolean | ((this: V) => boolean),
                method: string
            }
        },

        extensionApiDevtoolInformation?: {
            property?: string,
            method?: string,
            positionId?: (currentComponent: any) => string,
            helpText?: string,
        }
    }

    interface PropOptions<T=any> {
        validValues?: T[]
    }
}

declare module 'axios' {
    interface AxiosRequestConfig {
        // adds the shopware API version to the RequestConfig
        version?: number
    }
}
