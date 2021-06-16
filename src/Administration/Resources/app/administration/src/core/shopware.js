// merge 16.11.2020
/**
 * Shopware End Developer API
 * @module Shopware
 * @ignore
 */
const Bottle = require('bottlejs');

const ModuleFactory = require('src/core/factory/module.factory').default;
const ComponentFactory = require('src/core/factory/component.factory').default;
const TemplateFactory = require('src/core/factory/template.factory').default;
const EntityFactory = require('src/core/factory/entity.factory').default;
const StateFactory = require('src/core/factory/state.factory').default;
const ServiceFactory = require('src/core/factory/service.factory').default;
const ClassesFactory = require('src/core/factory/classes-factory').default;
const MixinFactory = require('src/core/factory/mixin.factory').default;
const FilterFactory = require('src/core/factory/filter.factory').default;
const DirectiveFactory = require('src/core/factory/directive.factory').default;
const LocaleFactory = require('src/core/factory/locale.factory').default;
const ShortcutFactory = require('src/core/factory/shortcut.factory').default;
const PluginBootFactory = require('src/core/factory/plugin-boot.factory').default;
const ApiServiceFactory = require('src/core/factory/api-service.factory').default;
const EntityDefinitionFactory = require('src/core/factory/entity-definition.factory').default;
const WorkerNotificationFactory = require('src/core/factory/worker-notification.factory').default;

const Feature = require('src/core/feature').default;
const ShopwareError = require('src/core/data/ShopwareError').default;
const ApiService = require('src/core/service/api.service').default;
const utils = require('src/core/service/util.service').default;
const FlatTreeHelper = require('src/core/helper/flattree.helper').default;
const SanitizerHelper = require('src/core/helper/sanitizer.helper').default;
const DeviceHelper = require('src/core/helper/device.helper').default;
const MiddlewareHelper = require('src/core/helper/middleware.helper').default;
const data = require('src/core/data/index').default;
const ApplicationBootstrapper = require('src/core/application').default;

const RefreshTokenHelper = require('src/core/helper/refresh-token.helper').default;
const HttpFactory = require('src/core/factory/http.factory').default;
const RepositoryFactory = require('src/core/data/repository-factory.data').default;
const ApiContextFactory = require('src/core/factory/api-context.factory').default;
const AppContextFactory = require('src/core/factory/app-context.factory').default;
const RouterFactory = require('src/core/factory/router.factory').default;
const ApiServices = require('src/core/service/api').default;
const ModuleFilterFactory = require('src/core/data/filter-factory.data').default;

const container = new Bottle({
    strict: true,
});

const application = new ApplicationBootstrapper(container);

application
    .addFactory('component', () => {
        return ComponentFactory;
    })
    .addFactory('template', () => {
        return TemplateFactory;
    })
    .addFactory('module', () => {
        return ModuleFactory;
    })
    .addFactory('entity', () => {
        return EntityFactory;
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

const Shopware = function Shopware() {
    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Module = {
        register: ModuleFactory.registerModule,
        getModuleRegistry: ModuleFactory.getModuleRegistry,
        getModuleRoutes: ModuleFactory.getModuleRoutes,
        getModuleByEntityName: ModuleFactory.getModuleByEntityName,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Component = {
        register: ComponentFactory.register,
        extend: ComponentFactory.extend,
        override: ComponentFactory.override,
        build: ComponentFactory.build,
        getTemplate: ComponentFactory.getComponentTemplate,
        getComponentRegistry: ComponentFactory.getComponentRegistry,
        getComponentHelper: ComponentFactory.getComponentHelper,
        registerComponentHelper: ComponentFactory.registerComponentHelper,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Template = {
        register: TemplateFactory.registerComponentTemplate,
        extend: TemplateFactory.extendComponentTemplate,
        override: TemplateFactory.registerTemplateOverride,
        getRenderedTemplate: TemplateFactory.getRenderedTemplate,
        find: TemplateFactory.findCustomTemplate,
        findOverride: TemplateFactory.findCustomTemplate,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Entity = {
        addDefinition: EntityFactory.addEntityDefinition,
        getDefinition: EntityFactory.getEntityDefinition,
        getDefinitionRegistry: EntityFactory.getDefinitionRegistry,
        getRawEntityObject: EntityFactory.getRawEntityObject,
        getPropertyBlacklist: EntityFactory.getPropertyBlacklist,
        getRequiredProperties: EntityFactory.getRequiredProperties,
        getAssociatedProperties: EntityFactory.getAssociatedProperties,
        getTranslatableProperties: EntityFactory.getTranslatableProperties,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.State = StateFactory();

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Mixin = {
        register: MixinFactory.register,
        getByName: MixinFactory.getByName,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Filter = {
        register: FilterFactory.register,
        getByName: FilterFactory.getByName,
        getRegistry: FilterFactory.getRegistry,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Directive = {
        register: DirectiveFactory.registerDirective,
        getByName: DirectiveFactory.getDirectiveByName,
        getDirectiveRegistry: DirectiveFactory.getDirectiveRegistry,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Locale = {
        register: LocaleFactory.register,
        extend: LocaleFactory.extend,
        getByName: LocaleFactory.getLocaleByName,
        getLocaleRegistry: LocaleFactory.getLocaleRegistry,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Shortcut = {
        getShortcutRegistry: ShortcutFactory.getShortcutRegistry,
        getPathByCombination: ShortcutFactory.getPathByCombination,
        register: ShortcutFactory.register,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Plugin = {
        addBootPromise: PluginBootFactory.addBootPromise,
        getBootPromises: PluginBootFactory.getBootPromises,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Service = ServiceFactory;

    /**
     * @memberOf module:Shopware
     * @type {module:core/service/utils}
     */
    this.Utils = utils;

    /**
     * @memberOf module:Shopware
     * @type {module:core/application}
     */
    this.Application = application;

    /**
     * @memberOf module:Shopware
     * @type {module:core/feature}
     */
    this.Feature = Feature;

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.ApiService = {
        register: ApiServiceFactory.register,
        getByName: ApiServiceFactory.getByName,
        getRegistry: ApiServiceFactory.getRegistry,
        getServices: ApiServiceFactory.getServices,
        has: ApiServiceFactory.has,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.EntityDefinition = {
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

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.WorkerNotification = {
        register: WorkerNotificationFactory.register,
        getRegistry: WorkerNotificationFactory.getRegistry,
        override: WorkerNotificationFactory.override,
        remove: WorkerNotificationFactory.remove,
        initialize: WorkerNotificationFactory.initialize,
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Defaults = {
        systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        defaultLanguageIds: ['2fbb5fe2e29a4d70aa5854ce7ce3e20b'],
        versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        storefrontSalesChannelTypeId: '8a243080f92e4c719546314b577cf82b',
        productComparisonTypeId: 'ed535e5722134ac1aa6524f73e26881b',
        apiSalesChannelTypeId: 'f183ee5650cf4bdb8a774337575067a6',
    };

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Data = data;

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Classes = ClassesFactory({
        ShopwareError: ShopwareError,
        ApiService: ApiService,
    },
    {
        /**
         * @memberOf module:Shopware.Classes
         * @type {Object}
         * @private
         */
        _private: {
            HttpFactory: HttpFactory,
            RepositoryFactory: RepositoryFactory,
            ApiContextFactory: ApiContextFactory,
            AppContextFactory: AppContextFactory,
            RouterFactory: RouterFactory,
            FilterFactory: ModuleFilterFactory,
        },
    });

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    this.Helper = {
        FlatTreeHelper: FlatTreeHelper,
        MiddlewareHelper: MiddlewareHelper,
        RefreshTokenHelper: RefreshTokenHelper,
        SanitizerHelper: SanitizerHelper,
        DeviceHelper: DeviceHelper,
    };
};

// hidden in prototype
Shopware.prototype = {
    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    get Context() {
        return this.State.get('context');
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     * @private
     */
    _private: {
        ApiServices: ApiServices,
    },
};

const ShopwareInstance = new Shopware();

window.Shopware = ShopwareInstance;
exports.default = ShopwareInstance;
module.exports = exports.default;
