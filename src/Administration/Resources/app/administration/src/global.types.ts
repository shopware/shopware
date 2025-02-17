/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable import/no-named-default */
/* eslint-disable @typescript-eslint/no-empty-object-type */
/* eslint-disable @typescript-eslint/no-unsafe-function-type */
import type { default as Bottle, Decorator } from 'bottlejs';
import type { NavigationGuardNext, RouteLocationNormalizedLoaded, RouteLocationRaw, Router } from 'vue-router';
// Import explicitly global types from meteor-admin-sdk
import '@shopware-ag/meteor-admin-sdk';
import type FeatureService from 'src/app/service/feature.service';
import type { LoginService } from 'src/core/service/login.service';
import type { AxiosInstance } from 'axios';
import type { ShopwareClass } from 'src/core/shopware';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
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
import type { ComponentInternalInstance } from 'vue';
import type { I18n } from 'vue-i18n';
import type {
    Store,
    mapActions as mapVuexActions,
    mapGetters as mapVuexGetters,
    mapMutations as mapVuexMutations,
    mapState as mapVuexState,
} from 'vuex';
import type { mapActions, mapState } from 'pinia';
import type * as mapErrors from 'src/app/service/map-errors.service';
import type JsonApiParserService from 'src/core/service/jsonapi-parser.service';
/* eslint-disable @typescript-eslint/no-unused-vars */
// Needed for the Editor types
import type { Editor as CoreEditor, EditorOptions } from '@tiptap/core';
import type Link from '@tiptap/extension-link';
/* eslint-enable @typescript-eslint/no-unused-vars */
import type { ComponentConfig } from './core/factory/async-component.factory';
import type StoreApiService from './core/service/api/store.api.service';
import type ShopwareDiscountCampaignService from './app/service/discount-campaign.service';
import type AppModulesService from './core/service/api/app-modules.service';
import type AclService from './app/service/acl.service';
import type EntityValidationService from './app/service/entity-validation.service';
import type CustomEntityDefinitionService from './app/service/custom-entity-definition.service';
import type CmsPageTypeService from './module/sw-cms/service/cms-page-type.service';
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
import type { UsageDataApiService } from './core/service/api/usage-data.api.service';
import type ConfigApiService from './core/service/api/config.api.service';
import type ImportExportService from './module/sw-import-export/service/importExport.service';
import type WorkerNotificationFactory from './core/factory/worker-notification.factory';
import type NotificationMixin from './app/mixin/notification.mixin';
import type ValidationMixin from './app/mixin/validation.mixin';
import type UserSettingsMixin from './app/mixin/user-settings.mixin';
import type SwInlineSnippetMixin from './app/mixin/sw-inline-snippet.mixin';
import type SalutationMixin from './app/mixin/salutation.mixin';
import type RuleContainerMixin from './app/mixin/rule-container.mixin';
import type RemoveApiErrorMixin from './app/mixin/remove-api-error.mixin';
import type PositionMixin from './app/mixin/position.mixin';
import type PlaceholderMixin from './app/mixin/placeholder.mixin';
import type ListingMixin from './app/mixin/listing.mixin';
import type CartNotificationMixin from './module/sw-order/mixin/cart-notification.mixin';
import type SwExtensionErrorMixin from './module/sw-extension/mixin/sw-extension-error.mixin';
import type CmsElementMixin from './module/sw-cms/mixin/sw-cms-element.mixin';
import type CmsStateMixin from './module/sw-cms/mixin/sw-cms-state.mixin';
import type GenericConditionMixin from './app/mixin/generic-condition.mixin';
import type SwFormFieldMixin from './app/mixin/form-field.mixin';
import type DiscardDetailPageChangesMixin from './app/mixin/discard-detail-page-changes.mixin';
import type PrivilegesService from './app/service/privileges.service';
import type BusinessEventsApiService from './core/service/api/business-events.api.service';
import type { FileValidationService } from './app/service/file-validation.service';
import type { DevtoolComponent } from './app/adapter/view/sw-vue-devtools';
import type { CmsPageStore } from './module/sw-cms/store/cms-page.store';
import type { TopBarButtonStore } from './app/store/topbar-button.store';
import type { TeaserPopoverStore } from './app/store/teaser-popover.store';
import type { AdminMenuStore } from './app/store/admin-menu.store';
import type { InAppPurchasesStore } from './app/store/in-app-purchase-checkout.store';
import type { CmsService } from './module/sw-cms/service/cms.service';
import type { ExtensionComponentSectionsStore } from './app/store/extension-component-sections.store';
import type { BlockOverrideStore } from './app/store/block-override.store';
import type { ExtensionEntryRoutes } from './app/store/extension-entry-routes.store';
import type { ExtensionSdkModules } from './app/store/extension-sdk-module.store';
import type { Extensions } from './app/store/extensions.store';
import type { ErrorStore } from './app/store/error.store';
import type { AdminHelpCenterStore } from './app/store/admin-help-center.store';
import type { ActionButtonsStore } from './app/store/action-buttons.store';
import type { ContextStore } from './app/store/context.store';
import type { LicenseViolationStore } from './app/store/license-violation.store';
import type { ExtensionMainModules } from './app/store/main-module.store';
import type { MarketingStore } from './app/store/marketing.store';
import type { SdkLocation } from './app/store/sdk-location.store';
import type { RuleConditionsConfig } from './app/store/rule-conditions-config.store';
import type { SettingsItems } from './app/store/settings-item.store';
import type { ShopwareApps } from './app/store/shopware-apps.store';
import type { System } from './app/store/system.store';
import type { ModalsStore } from './app/store/modals.store';
import type { MenuItemStore } from './app/store/menu-item.store';
import type { NotificationStore } from './app/store/notification.store';
import type { TabsStore } from './app/store/tabs.store';
import type { UsageData } from './app/store/usage-data.store';
import type { SessionStore } from './app/store/session.store';
import type { SwCategoryDetailStore } from './module/sw-category/page/sw-category-detail/store';
import type { SwSeoUrlStore } from './module/sw-settings-seo/component/sw-seo-url/store';
import type { ShopwareExtensionsStore } from './module/sw-extension/store/extensions.store';
import type { SwOrderDetailStore } from './module/sw-order/store/order-detail.store';
import type { SwOrderStore } from './module/sw-order/store/order.store';
import type { SwShippingDetailStore } from './module/sw-settings-shipping/page/sw-settings-shipping-detail/store';
import type { PaymentOverviewCardStore } from './module/sw-settings-payment/store/overview-cards.store';
import type { SwProductDetailStore } from './module/sw-product/page/sw-product-detail/store';
import type { SwProfileStore } from './module/sw-profile/store/sw-profile.store';
import type { SwPromotionDetailStore } from './module/sw-promotion-v2/page/sw-promotion-v2-detail/store';
import type { SwFlowStore } from './module/sw-flow/store/flow.store';
import type { SwBulkStore } from './module/sw-bulk-edit/store/sw-bulk-edit.store';

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
        id: string;
        salutationKey: string;
        displayName: string;
    };
    title: string;
    firstName: string;
    lastName: string;
    [key: string]: unknown;
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
    type $TSDangerUnknownObject = { [key: string | symbol]: unknown };

    /**
     * Mark some properties as required
     */
    type Require<T, K extends keyof T> = T & { [P in K]-?: T[P] };

    /**
     * Mark some properties as optional
     */
    type Optional<T, K extends keyof T> = T & { [P in K]?: T[P] };

    /**
     * Mark some properties as optional
     */
    type Remove<T, K extends keyof T> = T & { [P in K]?: never };

    /**
     * Make the Shopware object globally available
     */
    const Shopware: ShopwareClass;

    type Entity<EntityName extends keyof EntitySchema.Entities> = EntitySchema.Entity<EntityName>;
    type EntityCollection<EntityName extends keyof EntitySchema.Entities> = EntitySchema.EntityCollection<EntityName>;

    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface CustomShopwareProperties {}

    interface Window {
        Shopware: ShopwareClass;
        _features_: {
            [featureName: string]: boolean;
        };
        _inAppPurchases_: Record<string, string>;
        processingInactivityLogout?: boolean;
        _sw_extension_component_collection: DevtoolComponent[];
        // Only available with Vite
        startApplication: () => void;
    }

    const _features_: {
        [featureName: string]: boolean;
    };

    const _inAppPurchases_: Record<string, string>;

    /**
     * Define global container for the bottle.js containers
     */
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface ServiceContainer extends SubContainer<'service'> {
        loginService: LoginService;
        feature: FeatureService;
        menuService: $TSFixMe;
        privileges: PrivilegesService;
        customEntityDefinitionService: CustomEntityDefinitionService;
        cmsPageTypeService: CmsPageTypeService;
        acl: AclService;
        jsonApiParserService: typeof JsonApiParserService;
        validationService: $TSFixMe;
        entityValidationService: EntityValidationService;
        timezoneService: $TSFixMe;
        ruleConditionDataProviderService: RuleConditionService;
        productStreamConditionService: $TSFixMe;
        customFieldDataProviderService: $TSFixMe;
        extensionHelperService: ExtensionHelperService;
        languageAutoFetchingService: $TSFixMe;
        stateStyleDataProviderService: StateStyleService;
        searchTypeService: $TSFixMe;
        localeToLanguageService: $TSFixMe;
        entityMappingService: $TSFixMe;
        shortcutService: $TSFixMe;
        licenseViolationService: $TSFixMe;
        localeHelper: $TSFixMe;
        filterService: $TSFixMe;
        mediaDefaultFolderService: $TSFixMe;
        appAclService: $TSFixMe;
        appCmsService: $TSFixMe;
        entityHydrator: $TSFixMe;
        entityFactory: $TSFixMe;
        userService: UserApiService;
        shopwareDiscountCampaignService: ShopwareDiscountCampaignService;
        cmsService: CmsService;
        cmsElementFavorites: cmsElementFavoritesService;
        cmsBlockFavorites: cmsBlockFavoritesService;
        searchRankingService: $TSFixMe;
        searchPreferencesService: $TSFixMe;
        storeService: StoreApiService;
        contextStoreService: StoreContextService;
        checkoutStoreService: CheckoutStoreService;
        orderStateMachineService: OrderStateMachineApiService;
        repositoryFactory: RepositoryFactory;
        snippetService: $TSFixMe;
        recentlySearchService: $TSFixMe;
        extensionSdkService: ExtensionSdkService;
        appModulesService: AppModulesService;
        cartStoreService: CartStoreService;
        customSnippetApiService: CustomSnippetApiService;
        userActivityService: UserActivityService;
        filterFactory: FilterFactoryData;
        systemConfigApiService: SystemConfigApiService;
        usageDataService: UsageDataApiService;
        configService: ConfigApiService;
        importExport: ImportExportService;
        fileValidationService: FileValidationService;
        businessEventService: BusinessEventsApiService;
    }

    interface MixinContainer {
        notification: typeof NotificationMixin;
        validation: typeof ValidationMixin;
        'user-settings': typeof UserSettingsMixin;
        'sw-inline-snippet': typeof SwInlineSnippetMixin;
        salutation: typeof SalutationMixin;
        ruleContainer: typeof RuleContainerMixin;
        'remove-api-error': typeof RemoveApiErrorMixin;
        position: typeof PositionMixin;
        placeholder: typeof PlaceholderMixin;
        listing: typeof ListingMixin;
        'cart-notification': typeof CartNotificationMixin;
        'sw-extension-error': typeof SwExtensionErrorMixin;
        'cms-element': typeof CmsElementMixin;
        'cms-state': typeof CmsStateMixin;
        'generic-condition': typeof GenericConditionMixin;
        'sw-form-field': typeof SwFormFieldMixin;
        'discard-detail-page-changes': typeof DiscardDetailPageChangesMixin;
    }

    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface InitContainer extends SubContainer<'init'> {
        state: $TSFixMe; // has to be removed once we moved to vite
        router: $TSFixMe;
        httpClient: AxiosInstance;
    }
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface InitPostContainer extends SubContainer<'init-post'> {}
    interface InitPreContainer extends SubContainer<'init-pre'> {
        state: $TSFixMe;
    }
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface FactoryContainer extends SubContainer<'factory'> {
        component: typeof AsyncComponentFactory;
        template: $TSFixMe;
        module: typeof ModuleFactory;
        entity: $TSFixMe;
        state: () => FullState;
        serviceFactory: $TSFixMe;
        classesFactory: $TSFixMe;
        mixin: $TSFixMe;
        directive: typeof DirectiveFactory;
        filter: typeof FilterFactory;
        locale: typeof LocaleFactory;
        shortcut: $TSFixMe;
        plugin: $TSFixMe;
        apiService: typeof ApiServiceFactory;
        entityDefinition: typeof EntityDefinitionFactory;
        workerNotification: typeof WorkerNotificationFactory;
    }

    interface FilterTypes {
        asset: (value: string) => string;
        currency: $TSFixMeFunction;
        date: (value: string, options?: Intl.DateTimeFormatOptions) => string;
        'file-size': $TSFixMeFunction;
        'media-name': $TSFixMeFunction;
        salutation: (entity: SalutationFilterEntityType, fallbackSnippet: string) => string;
        'stock-color-variant': $TSFixMeFunction;
        striphtml: (value: string) => string;
        'thumbnail-size': $TSFixMeFunction;
        truncate: $TSFixMeFunction;
        'unicode-uri': $TSFixMeFunction;
        [key: string]: ((...args: any[]) => any) | undefined;
    }

    interface ComponentHelper {
        mapState: typeof mapState;
        mapActions: typeof mapActions;
        mapVuexState: typeof mapVuexState;
        mapVuexMutations: typeof mapVuexMutations;
        mapVuexGetters: typeof mapVuexGetters;
        mapVuexActions: typeof mapVuexActions;
        mapPropertyErrors: typeof mapErrors.mapPropertyErrors;
        mapSystemConfigErrors: typeof mapErrors.mapSystemConfigErrors;
        mapCollectionPropertyErrors: typeof mapErrors.mapCollectionPropertyErrors;
        mapPageErrors: typeof mapErrors.mapPageErrors;
    }

    /**
     * Define global state for the Vuex store
     * @deprecated tag:v6.8.0 - Will be removed use PiniaRootState instead
     */
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface VuexRootState {
        swCategoryDetail: $TSFixMe;
    }

    interface PiniaRootState {
        cmsPage: CmsPageStore;
        topBarButton: TopBarButtonStore;
        teaserPopover: TeaserPopoverStore;
        adminMenu: AdminMenuStore;
        inAppPurchaseCheckout: InAppPurchasesStore;
        extensionComponentSections: ExtensionComponentSectionsStore;
        blockOverride: BlockOverrideStore;
        extensionEntryRoutes: ExtensionEntryRoutes;
        extensionSdkModules: ExtensionSdkModules;
        extensions: Extensions;
        error: ErrorStore;
        context: ContextStore;
        adminHelpCenter: AdminHelpCenterStore;
        actionButtons: ActionButtonsStore;
        licenseViolation: LicenseViolationStore;
        extensionMainModules: ExtensionMainModules;
        marketing: MarketingStore;
        sdkLocation: SdkLocation;
        ruleConditionsConfig: RuleConditionsConfig;
        settingsItems: SettingsItems;
        shopwareApps: ShopwareApps;
        system: System;
        modals: ModalsStore;
        menuItem: MenuItemStore;
        notification: NotificationStore;
        tabs: TabsStore;
        usageData: UsageData;
        session: SessionStore;
        swCategoryDetail: SwCategoryDetailStore;
        swSeoUrl: SwSeoUrlStore;
        shopwareExtensions: ShopwareExtensionsStore;
        swOrderDetail: SwOrderDetailStore;
        swOrder: SwOrderStore;
        swShippingDetailStore: SwShippingDetailStore;
        paymentOverviewCard: PaymentOverviewCardStore;
        swProductDetail: SwProductDetailStore;
        swProfile: SwProfileStore;
        swPromotionDetail: SwPromotionDetailStore;
        swFlow: SwFlowStore;
        swBulkEdit: SwBulkStore;
    }

    /**
     * define global Component
     */
    type VueComponent = ComponentConfig;

    type apiContext = ContextStore['api'];

    type appContext = ContextStore['app'];

    /**
     * @see Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory
     */
    interface ShopwareHttpError {
        code: string;
        status: string;
        title: string;
        detail: string;
        meta?: {
            file: string;
            line: string;
            trace?: { [key: string]: string };
            parameters?: object;
            previous?: ShopwareHttpError;
        };
        trace?: { [key: string]: string };
    }

    interface StoreApiException extends ShopwareHttpError {
        meta?: ShopwareHttpError['meta'] & {
            documentationLink?: string;
        };
    }

    const flushPromises: () => Promise<void>;

    /**
     * @private This is a private method and should not be used outside of the test suite
     */
    const wrapTestComponent: (componentName: string, config?: { sync?: boolean }) => Promise<VueComponent>;
}

/**
 * Link global bottle.js container to the bottle.js container interface
 */
declare module 'bottlejs' {
    // Use the same module name as the import string
    type IContainerChildren = 'factory' | 'service' | 'init' | 'init-post' | 'init-pre';

    interface IContainer {
        factory: FactoryContainer;
        service: ServiceContainer;
        'init-pre': InitPreContainer;
        init: InitContainer;
        'init-post': InitPostContainer;
    }
}

interface CustomProperties extends ServiceContainer {
    $createTitle: (identifier?: string | null) => string;
    $router: Router;
    $store: Store<VuexRootState>;
    $route: RouteLocationNormalizedLoaded;
    $te: I18n<{}, {}, {}, string, true>['global']['te'];
    $tc: I18n<{}, {}, {}, string, true>['global']['t'];
    $t: I18n<{}, {}, {}, string, true>['global']['t'];
    $dataScope: () => ComponentInternalInstance['proxy'];
}

declare module '@vue/runtime-core' {
    // eslint-disable-next-line @typescript-eslint/no-shadow,@typescript-eslint/no-empty-interface
    interface App extends CustomProperties {}

    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface ComponentCustomProperties extends CustomProperties {}

    interface ComponentCustomOptions {
        shortcuts?: {
            [key: string]:
                | string
                | {
                      active: boolean | ((this: App) => boolean);
                      method: string;
                  };
        };

        extensionApiDevtoolInformation?: {
            property?: string;
            method?: string;
            positionId?: (currentComponent: any) => string;
            helpText?: string;
        };

        beforeRouteEnter?: (to: RouteLocationRaw, from: RouteLocationRaw, next: NavigationGuardNext) => void;
        beforeRouteLeave?: (to: RouteLocationRaw, from: RouteLocationRaw, next: NavigationGuardNext) => void;
    }

    interface PropOptions {
        validValues?: any[];
    }
}

declare module 'axios' {
    interface AxiosRequestConfig {
        // adds the shopware API version to the RequestConfig
        version?: number;
    }
}
