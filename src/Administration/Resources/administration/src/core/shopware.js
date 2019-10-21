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
const MixinFactory = require('src/core/factory/mixin.factory').default;
const FilterFactory = require('src/core/factory/filter.factory').default;
const DirectiveFactory = require('src/core/factory/directive.factory').default;
const LocaleFactory = require('src/core/factory/locale.factory').default;
const ShortcutFactory = require('src/core/factory/shortcut.factory').default;
const PluginBootFactory = require('src/core/factory/plugin-boot.factory').default;
const ApiServiceFactory = require('src/core/factory/api-service.factory').default;
const EntityDefinitionFactory = require('src/core/factory/entity-definition.factory').default;
const WorkerNotificationFactory = require('src/core/factory/worker-notification.factory').default;

const FeatureConfig = require('src/core/feature-config').default;
const ShopwareError = require('src/core/data/ShopwareError').default;
const ApiService = require('src/core/service/api.service').default;
const utils = require('src/core/service/util.service').default;
const FlatTreeHelper = require('src/core/helper/flattree.helper').default;
const InfiniteScrollingHelper = require('src/core/helper/infinite-scrolling.helper').default;
const SanitizerHelper = require('src/core/helper/sanitizer.helper').default;
const DeviceHelper = require('src/core/helper/device.helper').default;
const MiddlewareHelper = require('src/core/helper/middleware.helper').default;
const data = require('src/core/data-new/index').default;
const dataDeprecated = require('src/core/data/index').default;
const ApplicationBootstrapper = require('src/core/application').default;

const RefreshTokenHelper = require('src/core/helper/refresh-token.helper').default;
const HttpFactory = require('src/core/factory/http.factory').default;
const RepositoryFactory = require('src/core/data-new/repository-factory.data').default;
const ContextFactory = require('src/core/factory/context.factory').default;
const RouterFactory = require('src/core/factory/router.factory').default;
const ApiServices = require('src/core/service/api').default;

const container = new Bottle({
    strict: true
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

const Shopware = {
    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Module: {
        register: ModuleFactory.registerModule,
        getModuleRegistry: ModuleFactory.getModuleRegistry,
        getModuleRoutes: ModuleFactory.getModuleRoutes,
        getModuleByEntityName: ModuleFactory.getModuleByEntityName
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Component: {
        register: ComponentFactory.register,
        extend: ComponentFactory.extend,
        override: ComponentFactory.override,
        build: ComponentFactory.build,
        getTemplate: ComponentFactory.getComponentTemplate,
        getComponentRegistry: ComponentFactory.getComponentRegistry,
        getComponentHelper: ComponentFactory.getComponentHelper,
        registerComponentHelper: ComponentFactory.registerComponentHelper
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Template: {
        register: TemplateFactory.registerComponentTemplate,
        extend: TemplateFactory.extendComponentTemplate,
        override: TemplateFactory.registerTemplateOverride,
        getRenderedTemplate: TemplateFactory.getRenderedTemplate,
        find: TemplateFactory.findCustomTemplate,
        findOverride: TemplateFactory.findCustomTemplate
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Entity: {
        addDefinition: EntityFactory.addEntityDefinition,
        getDefinition: EntityFactory.getEntityDefinition,
        getDefinitionRegistry: EntityFactory.getDefinitionRegistry,
        getRawEntityObject: EntityFactory.getRawEntityObject,
        getPropertyBlacklist: EntityFactory.getPropertyBlacklist,
        getRequiredProperties: EntityFactory.getRequiredProperties,
        getAssociatedProperties: EntityFactory.getAssociatedProperties,
        getTranslatableProperties: EntityFactory.getTranslatableProperties
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    State: {
        registerStore: StateFactory.registerStore,
        getStore: StateFactory.getStore,
        getStoreRegistry: StateFactory.getStoreRegistry
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Mixin: {
        register: MixinFactory.register,
        getByName: MixinFactory.getByName
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Filter: {
        register: FilterFactory.register,
        getByName: FilterFactory.getByName
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Directive: {
        register: DirectiveFactory.registerDirective,
        getByName: DirectiveFactory.getDirectiveByName
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Locale: {
        register: LocaleFactory.register,
        extend: LocaleFactory.extend,
        getByName: LocaleFactory.getLocaleByName
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Shortcut: {
        getShortcutRegistry: ShortcutFactory.getShortcutRegistry,
        getPathByCombination: ShortcutFactory.getPathByCombination,
        register: ShortcutFactory.register
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Plugin: {
        addBootPromise: PluginBootFactory.addBootPromise,
        getBootPromises: PluginBootFactory.getBootPromises
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Service: (serviceName) => {
        this.get = (name) => application.getContainer('service')[name];
        this.list = () => application.getContainer('service').$list();
        this.register = (name, service) => application.addServiceProvider(name, service);
        this.registerMiddleware = (...args) => application.addServiceProviderMiddleware(...args);
        this.registerDecorator = (...args) => application.addServiceProviderDecorator(...args);

        return serviceName ? this.get(serviceName) : this;
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    get Context() {
        return application.getContainer('service').context;
    },

    /**
     * @memberOf module:Shopware
     * @type {module:core/service/utils}
     */
    Utils: utils,

    /**
     * @memberOf module:Shopware
     * @type {module:core/application}
     */
    Application: application,

    /**
     * @memberOf module:Shopware
     * @type {module:core/feature-config}
     */
    FeatureConfig: FeatureConfig,

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    ApiService: {
        register: ApiServiceFactory.register,
        getByName: ApiServiceFactory.getByName,
        getRegistry: ApiServiceFactory.getRegistry,
        getServices: ApiServiceFactory.getServices,
        has: ApiServiceFactory.has
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    EntityDefinition: {
        getScalarTypes: EntityDefinitionFactory.getScalarTypes,
        getJsonTypes: EntityDefinitionFactory.getJsonTypes,
        getDefinitionRegistry: EntityDefinitionFactory.getDefinitionRegistry,
        get: EntityDefinitionFactory.get,
        add: EntityDefinitionFactory.add,
        remove: EntityDefinitionFactory.remove,
        getTranslatedFields: EntityDefinitionFactory.getTranslatedFields,
        getAssociationFields: EntityDefinitionFactory.getAssociationFields,
        getRequiredFields: EntityDefinitionFactory.getRequiredFields
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    WorkerNotification: {
        register: WorkerNotificationFactory.register,
        getRegistry: WorkerNotificationFactory.getRegistry,
        override: WorkerNotificationFactory.override,
        remove: WorkerNotificationFactory.remove,
        initialize: WorkerNotificationFactory.initialize
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Defaults: {
        systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        defaultLanguageIds: ['2fbb5fe2e29a4d70aa5854ce7ce3e20b'],
        versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425'
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Data: data,

    /**
     * @memberOf module:Shopware
     * @type {Object}
     * @deprecated 6.1
     */
    DataDeprecated: dataDeprecated,

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Classes: {
        _private: {
            HttpFactory: HttpFactory,
            RepositoryFactory: RepositoryFactory,
            ContextFactory: ContextFactory,
            RouterFactory: RouterFactory
        },
        ShopwareError: ShopwareError,
        ApiService: ApiService
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Helper: {
        FlatTreeHelper: FlatTreeHelper,
        InfiniteScrollingHelper: InfiniteScrollingHelper,
        MiddlewareHelper: MiddlewareHelper,
        RefreshTokenHelper: RefreshTokenHelper,
        SanitizerHelper: SanitizerHelper,
        DeviceHelper: DeviceHelper
    },

    /**
     * @memberOf module:Shopware
     * @private
     * @type {Object}
     */
    _private: {
        ApiServices: ApiServices
    }
};

window.Shopware = Shopware;
exports.default = Shopware;
module.exports = exports.default;
