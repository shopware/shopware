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
import { MenuItemState } from './app/state/menu-item.store';
import { ModalsState } from './app/state/modals.store';
import { ExtensionSdkModuleState } from './app/state/extension-sdk-module.store';
import { MainModuleState } from './app/state/main-module.store';
import { ActionButtonState } from './app/state/action-button.store';

// trick to make it an "external module" to support global type extension
export {};

// base methods for subContainer
interface SubContainer<ContainerName extends string> {
    $decorator(name: string | Decorator, func?: Decorator): this;
    $register(Obj: Bottle.IRegisterableObject): this;
    $list(): (keyof Bottle.IContainer[ContainerName])[];
}

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
        acl: $TSFixMe,
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
        shopwareDiscountCampaignService: $TSFixMe,
        searchRankingService: $TSFixMe,
        searchPreferencesService: $TSFixMe,
        storeService: $TSFixMe,
        repositoryFactory: RepositoryFactory,
        snippetService: $TSFixMe,
        extensionStoreActionService: $TSFixMe,
        recentlySearchService: $TSFixMe,
        extensionSdkService: ExtensionSdkService,
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
        salutation: $TSFixMeFunction,
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
        session: {
            currentLocale: string,
            currentUser: $TSFixMe,
        },
        menuItem: MenuItemState,
        extensionSdkModules: ExtensionSdkModuleState,
        extensionMainModules: MainModuleState,
        modals: ModalsState,
        actionButtons: ActionButtonState,
    }

    /**
     * define global Component
     */
    type VueComponent = ComponentConfig;

    type apiContext = ContextState['api'];

    type appContext = ContextState['app'];
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
}
