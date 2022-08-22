/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable import/no-named-default */
import type { default as Bottle, Decorator } from 'bottlejs';
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
import type { ExtensionsState } from './app/state/extensions.store';
import type { ComponentConfig } from './core/factory/component.factory';
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
import type AclService from './app/service/acl.service';
import type { ShopwareAppsState } from './app/state/shopware-apps.store';
import type { SdkLocationState } from './app/state/sdk-location.store';

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
        acl: AclService,
        jsonApiParserService: $TSFixMe,
        validationService: $TSFixMe,
        timezoneService: $TSFixMe,
        ruleConditionDataProviderService: $TSFixMe,
        productStreamConditionService: $TSFixMe,
        customFieldDataProviderService: $TSFixMe,
        extensionHelperService: $TSFixMe,
        languageAutoFetchingService: $TSFixMe,
        stateStyleDataProviderService: $TSFixMe,
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
        shopwareDiscountCampaignService: ShopwareDiscountCampaignService,
        searchRankingService: $TSFixMe,
        searchPreferencesService: $TSFixMe,
        storeService: StoreApiService,
        repositoryFactory: RepositoryFactory,
        snippetService: $TSFixMe,
        recentlySearchService: $TSFixMe,
        extensionSdkService: ExtensionSdkService,
        appModulesService: AppModulesService,
    }
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface InitContainer extends SubContainer<'init'>{
        state: $TSFixMe,
        router: $TSFixMe,
        httpClient: AxiosInstance,
    }
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface FactoryContainer extends SubContainer<'factory'>{
        component: $TSFixMe,
        template: $TSFixMe,
        module: $TSFixMe,
        entity: $TSFixMe,
        state: $TSFixMe,
        serviceFactory: $TSFixMe,
        classesFactory: $TSFixMe,
        mixin: $TSFixMe,
        filter: $TSFixMe,
        directive: $TSFixMe,
        locale: $TSFixMe,
        shortcut: $TSFixMe,
        plugin: $TSFixMe,
        apiService: $TSFixMe,
        entityDefinition: $TSFixMe,
        workerNotification: $TSFixMe,
    }

    interface FilterTypes {
        asset: (value: string) => string,
        currency: $TSFixMeFunction,
        date: (value: string, options: Intl.DateTimeFormatOptions) => string,
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
        session: {
            currentUser: $TSFixMe,
            userPending: boolean,
            languageId: string,
            currentLocale: string|null,
        },
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
        coreRoute: boolean,
        type: ModuleTypes,
        flag: string,
        isChildren: boolean,
        routeKey: string,
    }
}

/**
 * Extend this context of vue components with service container types (from inject)
 * and plugins
 */
declare module 'vue/types/vue' {
    interface Vue extends ServiceContainer {
        $router: VueRouter
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
