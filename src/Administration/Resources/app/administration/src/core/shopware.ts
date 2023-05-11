/**
 * @package admin
 *
 * Shopware End Developer API
 * @module Shopware
 * @ignore
 */
import Bottle from 'bottlejs';

import ModuleFactory from 'src/core/factory/module.factory';
import AsyncComponentFactory from 'src/core/factory/async-component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import StateFactory from 'src/core/factory/state.factory';
import ServiceFactory from 'src/core/factory/service.factory';
import ClassesFactory from 'src/core/factory/classes-factory';
import MixinFactory from 'src/core/factory/mixin.factory';
import FilterFactory from 'src/core/factory/filter.factory';
import DirectiveFactory from 'src/core/factory/directive.factory';
import LocaleFactory from 'src/core/factory/locale.factory';
import ShortcutFactory from 'src/core/factory/shortcut.factory';
import PluginBootFactory from 'src/core/factory/plugin-boot.factory';
import ApiServiceFactory from 'src/core/factory/api-service.factory';
import EntityDefinitionFactory from 'src/core/factory/entity-definition.factory';
import WorkerNotificationFactory from 'src/core/factory/worker-notification.factory';

import Feature from 'src/core/feature';
import ShopwareError from 'src/core/data/ShopwareError';
import ApiService from 'src/core/service/api.service';
import utils from 'src/core/service/util.service';
import FlatTreeHelper from 'src/core/helper/flattree.helper';
import SanitizerHelper from 'src/core/helper/sanitizer.helper';
import DeviceHelper from 'src/core/helper/device.helper';
import MiddlewareHelper from 'src/core/helper/middleware.helper';
import data from 'src/core/data/index';
import ApplicationBootstrapper from 'src/core/application';

import RefreshTokenHelper from 'src/core/helper/refresh-token.helper';
import HttpFactory from 'src/core/factory/http.factory';
import RepositoryFactory from 'src/core/data/repository-factory.data';
import ApiContextFactory from 'src/core/factory/api-context.factory';
import AppContextFactory from 'src/core/factory/app-context.factory';
import RouterFactory from 'src/core/factory/router.factory';
import ApiServices from 'src/core/service/api';
import ModuleFilterFactory from 'src/core/data/filter-factory.data';
import ExtensionApi from './extension-api';

/** Initialize feature flags at the beginning */
if (window.hasOwnProperty('_features_')) {
    Feature.init(_features_);
}

// strict mode was set to false because it was defined wrong previously
Bottle.config = { strict: false };
const container = new Bottle();

const application = new ApplicationBootstrapper(container);

application
    .addFactory('component', () => {
        return AsyncComponentFactory;
    })
    .addFactory('template', () => {
        return TemplateFactory;
    })
    .addFactory('module', () => {
        return ModuleFactory;
    })
    .addFactory('state', () => {
        return StateFactory;
    })
    .addFactory('serviceFactory', () => {
        return ServiceFactory;
    })
    .addFactory('classesFactory', () => {
        return ClassesFactory;
    })
    .addFactory('mixin', () => {
        return MixinFactory;
    })
    .addFactory('filter', () => {
        return FilterFactory;
    })
    .addFactory('directive', () => {
        return DirectiveFactory;
    })
    .addFactory('locale', () => {
        return LocaleFactory;
    })
    .addFactory('shortcut', () => {
        return ShortcutFactory;
    })
    .addFactory('plugin', () => {
        return PluginBootFactory;
    })
    .addFactory('apiService', () => {
        return ApiServiceFactory;
    })
    .addFactory('entityDefinition', () => {
        return EntityDefinitionFactory;
    })
    .addFactory('workerNotification', () => {
        return WorkerNotificationFactory;
    });

class ShopwareClass {
    public Module = {
        register: ModuleFactory.registerModule,
        getModuleRegistry: ModuleFactory.getModuleRegistry,
        getModuleRoutes: ModuleFactory.getModuleRoutes,
        getModuleByEntityName: ModuleFactory.getModuleByEntityName,
    };

    public Component = {
        register: AsyncComponentFactory.register,
        extend: AsyncComponentFactory.extend,
        override: AsyncComponentFactory.override,
        build: AsyncComponentFactory.build,
        wrapComponentConfig: AsyncComponentFactory.wrapComponentConfig,
        getTemplate: AsyncComponentFactory.getComponentTemplate,
        getComponentRegistry: AsyncComponentFactory.getComponentRegistry,
        getComponentHelper: AsyncComponentFactory.getComponentHelper,
        registerComponentHelper: AsyncComponentFactory.registerComponentHelper,
        markComponentAsSync: AsyncComponentFactory.markComponentAsSync,
        isSyncComponent: AsyncComponentFactory.isSyncComponent,
    };

    public Template = {
        register: TemplateFactory.registerComponentTemplate,
        extend: TemplateFactory.extendComponentTemplate,
        override: TemplateFactory.registerTemplateOverride,
        getRenderedTemplate: TemplateFactory.getRenderedTemplate,
    };

    public State = StateFactory();

    public Mixin = {
        register: MixinFactory.register,
        getByName: MixinFactory.getByName,
    };

    public Filter = {
        register: FilterFactory.register,
        getByName: FilterFactory.getByName,
        getRegistry: FilterFactory.getRegistry,
    };

    public Directive = {
        register: DirectiveFactory.registerDirective,
        getByName: DirectiveFactory.getDirectiveByName,
        getDirectiveRegistry: DirectiveFactory.getDirectiveRegistry,
    };

    public Locale = {
        register: LocaleFactory.register,
        extend: LocaleFactory.extend,
        getByName: LocaleFactory.getLocaleByName,
        getLocaleRegistry: LocaleFactory.getLocaleRegistry,
    };

    public Shortcut = {
        getShortcutRegistry: ShortcutFactory.getShortcutRegistry,
        getPathByCombination: ShortcutFactory.getPathByCombination,
        register: ShortcutFactory.register,
    };

    public Plugin = {
        addBootPromise: PluginBootFactory.addBootPromise,
        getBootPromises: PluginBootFactory.getBootPromises,
    };

    public Service = ServiceFactory;

    public Utils = utils;

    public Application = application;

    public Feature = Feature;

    public ApiService = {
        register: ApiServiceFactory.register,
        getByName: ApiServiceFactory.getByName,
        getRegistry: ApiServiceFactory.getRegistry,
        getServices: ApiServiceFactory.getServices,
        has: ApiServiceFactory.has,
    };

    public EntityDefinition = {
        getScalarTypes: EntityDefinitionFactory.getScalarTypes,
        getJsonTypes: EntityDefinitionFactory.getJsonTypes,
        getDefinitionRegistry: EntityDefinitionFactory.getDefinitionRegistry,
        has: EntityDefinitionFactory.has,
        get: EntityDefinitionFactory.get,
        add: EntityDefinitionFactory.add,
        remove: EntityDefinitionFactory.remove,
        getTranslatedFields: EntityDefinitionFactory.getTranslatedFields,
        getAssociationFields: EntityDefinitionFactory.getAssociationFields,
        getRequiredFields: EntityDefinitionFactory.getRequiredFields,
    };

    public ExtensionAPI = ExtensionApi;

    public WorkerNotification = {
        register: WorkerNotificationFactory.register,
        getRegistry: WorkerNotificationFactory.getRegistry,
        override: WorkerNotificationFactory.override,
        remove: WorkerNotificationFactory.remove,
        initialize: WorkerNotificationFactory.initialize,
    };

    public Defaults = {
        systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        defaultLanguageIds: ['2fbb5fe2e29a4d70aa5854ce7ce3e20b'],
        versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        storefrontSalesChannelTypeId: '8a243080f92e4c719546314b577cf82b',
        productComparisonTypeId: 'ed535e5722134ac1aa6524f73e26881b',
        apiSalesChannelTypeId: 'f183ee5650cf4bdb8a774337575067a6',
        defaultSalutationId: 'ed643807c9f84cc8b50132ea3ccb1c3b',
    };

    public Data = data;

    public Classes = {
        ShopwareError,
        ApiService,
        _private: {
            HttpFactory,
            RepositoryFactory,
            ApiContextFactory,
            AppContextFactory,
            RouterFactory,
            FilterFactory: ModuleFilterFactory,
        },
    };

    public Helper = {
        FlatTreeHelper: FlatTreeHelper,
        MiddlewareHelper: MiddlewareHelper,
        RefreshTokenHelper: RefreshTokenHelper,
        SanitizerHelper: SanitizerHelper,
        DeviceHelper: DeviceHelper,
    };

    public get Context(): VuexRootState['context'] {
        return this.State.get('context');
    }

    public _private = {
        ApiServices: ApiServices,
    };
}

const ShopwareInstance = new ShopwareClass();

window.Shopware = ShopwareInstance;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ShopwareInstance;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { ShopwareClass };
