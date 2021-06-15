/**
 * @module core/application
 */

/**
 * The application bootstrapper bootstraps the application and registers the necessary
 * and optional parts of the application in a shared DI container which provides you
 * with an easy-to-use way to add new services as well as decoration these services.
 *
 * The bootstrapper provides you with the ability to register middleware for all or specific
 * services too.
 *
 * @class
 * @memberOf module:core/application
 */
class ApplicationBootstrapper {
    /**
     * Provides the necessary class properties for the class to work probably
     *
     * @constructor
     * @param {Bottle} container
     */
    constructor(container) {
        const noop = () => {};
        this.$container = container;

        this.view = null;

        // Create an empty DI container for the core initializers & services, so we can separate the core initializers
        // and the providers
        this.$container.service('service', noop);
        this.$container.service('init', noop);
        this.$container.service('factory', noop);
    }

    /**
     * Returns all containers. Use this method if you're want to get initializers in your services.
     *
     * @param {String=} containerName Name of the nested container. "init" & "service" are the core containers.
     * @returns {Bottle.IContainer}
     */
    getContainer(containerName) {
        const containerNames = this.$container.list();

        if (containerNames.indexOf(containerName) !== -1) {
            return this.$container.container[containerName];
        }
        return this.$container.container;
    }

    /**
     * Adds a factory to the application. A factory creates objects for the domain.
     *
     * The factory will be registered in a nested DI container.
     *
     * @example
     * Shopware.Application.addFactory('module', (container) => {
     *    return ModuleFactory();
     * });
     *
     * @param {String} name Name of the factory
     * @param {Function} factory Factory method
     * @returns {module:core/application.ApplicationBootstrapper}
     */
    addFactory(name, factory) {
        this.$container.factory(`factory.${name}`, factory.bind(this));

        return this;
    }

    /**
     * Registers a factory middleware for either every factory in the container or a defined one.
     *
     * @example
     * Shopware.Application.addFactoryMiddleware((container, next) => {
     *    // Do something with the container
     *    next();
     * });
     *
     * @example
     * Shopware.Application.addFactoryMiddleware('module', (service, next) => {
     *    // Do something with the service
     *    next();
     * });
     *
     * @param args
     * @returns {module:core/application.ApplicationBootstrapper}
     */
    addFactoryMiddleware(...args) {
        return this._addMiddleware('factory', args);
    }

    /**
     * Registers a decorator for either every factory in the container or a defined one.
     *
     * @example
     * Shopware.Application.addFactoryDecorator((container, next) => {
     *    // Do something with the container
     *    next();
     * });
     *
     * @example
     * Shopware.Application.addFactoryDecorator('module', (service, next) => {
     *    // Do something with the service
     *    next();
     * });
     *
     * @param args
     * @returns {module:core/application.ApplicationBootstrapper}
     */
    addFactoryDecorator(...args) {
        return this._addDecorator('factory', args);
    }

    /**
     * Adds an initializer to the application. An initializer is a necessary part of the application which needs to be
     * initialized before we can boot up the application.
     *
     * The initializer will be registered in a nested DI container.
     *
     * @example
     * Shopware.Application.addInitializer('httpClient', (container) => {
     *    return HttpFactory(container.apiContext);
     * });
     *
     * @param {String} name Name of the initializer
     * @param {Function} initializer Factory method
     * @returns {module:core/application.ApplicationBootstrapper}
     */
    addInitializer(name, initializer) {
        this.$container.factory(`init.${name}`, initializer.bind(this));
        return this;
    }

    /**
     * Registers optional services & provider for the application. Services are usually
     * API gateways but can be a simple service.
     *
     * The service will be added to a nested DI container.
     *
     * @example
     * Shopware.Application.addServiceProvider('productService', (container) => {
     *    return new ProductApiService(container.mediaService);
     * });
     *
     * @param {String} name Name of the service
     * @param {Function} provider Factory method for the service
     * @returns {module:core/application.ApplicationBootstrapper}
     */
    addServiceProvider(name, provider) {
        this.$container.factory(`service.${name}`, provider.bind(this));
        return this;
    }

    registerConfig(config) {
        this.registerApiContext(config.apiContext);
        this.registerAppContext(config.appContext);

        return this;
    }

    /**
     * Registers the api context (api path, path to resources etc.)
     *
     * @param {Object} context
     * @returns {ApplicationBootstrapper}
     */
    registerApiContext(context) {
        Shopware.Context.api = Shopware.Classes._private.ApiContextFactory(context);

        return this;
    }

    /**
     * Registers the app context (firstRunWizard, etc.)
     *
     * @param {Object} context
     * @returns {ApplicationBootstrapper}
     */
    registerAppContext(context) {
        Shopware.Context.app = Shopware.Classes._private.AppContextFactory(context);

        return this;
    }

    /**
     * Registers a service provider middleware for either every service provider in the container or a defined one.
     *
     * @example
     * Shopware.Application.addServiceProviderMiddleware((container, next) => {
     *    // Do something with the container
     *    next();
     * });
     *
     * @example
     * Shopware.Application.addServiceProviderMiddleware('productService', (service, next) => {
     *    // Do something with the service
     *    next();
     * });
     *
     * @param args
     * @returns {module:core/application.ApplicationBootstrapper}
     */
    addServiceProviderMiddleware(...args) {
        return this._addMiddleware('service', args);
    }

    /**
     * Helper method which registers a middleware
     *
     * @param {String} containerName
     * @param {Array} args
     * @returns {module:core/application.ApplicationBootstrapper}
     * @private
     */
    _addMiddleware(containerName, args) {
        const name = (args.length > 1 ? `${containerName}.${args[0]}` : containerName);
        const middlewareFn = (args.length > 1 ? args[1] : args[0]);

        this.$container.middleware(name, middlewareFn);

        return this;
    }

    /**
     * Initializes the feature flags from context settings
     *
     * @returns {module:core/application.ApplicationBootstrapper}
     */
    initializeFeatureFlags() {
        Shopware.Feature.init(Shopware.Context.app.features);

        return this;
    }

    /**
     * Registers a service provider decorator for either every service provider in the container or a defined one.
     *
     * @example
     * Shopware.Application.addServiceProviderDecorator((container, next) => {
     *    // Do something with the container
     *    next();
     * });
     *
     * @example
     * Shopware.Application.addServiceProviderDecorator('productService', (service, next) => {
     *    // Do something with the service
     *    next();
     * });
     *
     * @param args
     * @returns {module:core/application.ApplicationBootstrapper}
     */
    addServiceProviderDecorator(...args) {
        return this._addDecorator('service', args);
    }

    /**
     * Helper method which registers a decorator
     *
     * @param {String} containerName
     * @param {Array} args
     * @returns {module:core/application.ApplicationBootstrapper}
     * @private
     */
    _addDecorator(containerName, args) {
        const name = (args.length > 1 ? `${containerName}.${args[0]}` : containerName);
        const middlewareFn = (args.length > 1 ? args[1] : args[0]);

        this.$container.decorator(name, middlewareFn);

        return this;
    }

    /**
     * Starts the bootstrapping process of the application.
     *
     * @param {Object} [config={}]
     * @returns {module:core/application.ApplicationBootstrapper}
     */
    start(config = {}) {
        return this.initState()
            .registerConfig(config)
            .initializeFeatureFlags()
            .startBootProcess();
    }

    /**
     * Get the global state
     * @returns {Object}
     */
    initState() {
        const initaliziation = this.getContainer('init').state;

        if (initaliziation) {
            return this;
        }

        throw new Error('State could not be initialized');
    }

    /**
     * Returns the root of the application e.g. a new Vue instance
     *
     * @returns {Boolean|Vue}
     */
    getApplicationRoot() {
        if (!this.view.root) {
            return false;
        }

        return this.view.root;
    }

    setViewAdapter(viewAdapterInstance) {
        this.view = viewAdapterInstance;
    }

    /**
     * Boot the application depending on login status
     *
     * @returns {Promise<module:core/application.ApplicationBootstrapper>}
     */
    startBootProcess() {
        const loginService = this.getContainer('service').loginService;
        const isUserLoggedIn = loginService.isLoggedIn();

        // if user is not logged in
        if (!isUserLoggedIn) {
            loginService.logout();
            return this.bootLogin();
        }

        return this.bootFullApplication();
    }

    /**
     * Boot the login.
     *
     * @returns {Promise<module:core/application.ApplicationBootstrapper>}
     */
    bootLogin() {
        // set force reload after successful login
        sessionStorage.setItem('sw-login-should-reload', 'true');

        /**
         * Login Application Booting:
         *
         * 1. Initialize all login initializer
         * 2. Initialize the conversion of dependencies in view adapter
         * 3. Create the application root
         */

        return this.initializeLoginInitializer()
            .then(() => this.view.initDependencies())
            .then(() => this.createApplicationRoot())
            .catch((error) => this.createApplicationRootError(error));
    }

    /**
     * Boot the whole application.
     *
     * @returns {Promise<module:core/application.ApplicationBootstrapper>}
     */
    bootFullApplication() {
        const initContainer = this.getContainer('init');

        /**
         * Normal Application Booting:
         *
         * 1. Initialize all initializer
         * 2. Load plugins
         * 3. Wait until plugin promises are resolved
         * 4. Initialize the conversion of dependencies in view adapter
         * 5. Create the application root
         */

        return this.initializeInitializers(initContainer)
            .then(() => this.loadPlugins())
            .then(() => Promise.all(Shopware.Plugin.getBootPromises()))
            .then(() => this.view.initDependencies())
            .then(() => this.createApplicationRoot())
            .catch((error) => this.createApplicationRootError(error));
    }

    /**
     * Creates the application root and injects the provider container into the
     * view instance to keep the dependency injection of Vue.js in place.
     *
     * @returns {Promise<module:core/application.ApplicationBootstrapper>}
     */
    createApplicationRoot() {
        const initContainer = this.getContainer('init');
        const router = initContainer.router.getRouterInstance();

        // We're in a test environment, we're not needing an application root
        if (Shopware.Context.app.environment === 'testing') {
            return Promise.resolve(this);
        }

        this.view.init(
            '#app',
            router,
            this.getContainer('service'),
        );

        const firstRunWizard = Shopware.Context.app.firstRunWizard;
        if (firstRunWizard && !router.history.current.name.startsWith('sw.first.run.wizard.')) {
            router.push({
                name: 'sw.first.run.wizard.index',
            });
        }

        return Promise.resolve(this);
    }

    /**
     * Creates the application root and show the error message.
     *
     * @returns {Promise<module:core/application.ApplicationBootstrapper>}
     */
    createApplicationRootError(error) {
        console.error(error);
        const container = this.getContainer('init');
        const router = container.router.getRouterInstance();

        this.view.init(
            '#app',
            router,
            this.getContainer('service'),
        );

        this.view.root.initError = error;

        router.push({
            name: 'error',
        });
    }

    /**
     * Initialize the initializers right away cause these are the mandatory services for the application
     * to boot successfully.
     *
     * @private
     * @param {Bottle.IContainer} container Bottle container
     * @param {String} [prefix='init']
     * @returns {Promise<any[]>}
     */
    initializeInitializers(container, prefix = 'init') {
        const services = container.$list().map((serviceName) => {
            return `${prefix}.${serviceName}`;
        });
        this.$container.digest(services);

        const asyncInitializers = this.getAsyncInitializers(container);
        return Promise.all(asyncInitializers);
    }

    /**
     * Initialize the initializers right away cause these are the mandatory services for the application
     * to boot successfully.
     *
     * @private
     * @param {Bottle.IContainer} container Bottle container
     * @param {String} [prefix='init']
     * @returns {Promise<any[]>}
     */
    initializeLoginInitializer() {
        const loginInitializer = [
            'login',
            'baseComponents',
            'locale',
            'apiServices',
            'svgIcons',
        ];

        const initContainer = this.getContainer('init');
        loginInitializer.forEach((key) => {
            const exists = initContainer.hasOwnProperty(key);

            if (!exists) {
                console.error(`The initializer "${key}" does not exists`);
            }
        });

        this.$container.digest(loginInitializer.map(key => `init.${key}`));

        const asyncInitializers = this.getAsyncInitializers(loginInitializer);
        return Promise.all(asyncInitializers);
    }

    getAsyncInitializers(initializer) {
        const initContainer = this.getContainer('init');
        const asyncInitializers = [];
        Object.keys(initializer).forEach((serviceKey) => {
            const service = initContainer[serviceKey];
            if (service && service.constructor.name === 'Promise') {
                asyncInitializers.push(service);
            }
        });

        return asyncInitializers;
    }

    /**
     * Load all plugins from the server and inject them into the Site.
     * @private
     * @returns {Promise<any[][]>}
     */
    async loadPlugins() {
        const isDevelopmentMode = process.env.NODE_ENV;

        let plugins;
        // only in webpack dev mode
        if (isDevelopmentMode === 'development') {
            const response = await fetch('./sw-plugin-dev.json');
            plugins = await response.json();
        } else {
            plugins = Shopware.Context.app.config.bundles;
        }

        const injectAllPlugins = Object.values(plugins).map((plugin) => this.injectPlugin(plugin));

        return Promise.all(injectAllPlugins);
    }

    /**
     * Inject plugin scripts and styles
     * @private
     * @param {Object} plugin
     * @returns {Promise<any[]>}
     */
    async injectPlugin(plugin) {
        let allScripts = [];
        let allStyles = [];

        // load multiple js scripts
        if (plugin.js && Array.isArray(plugin.js)) {
            allScripts = plugin.js.map(src => this.injectJs(src));
        } else if (plugin.js) {
            allScripts.push(this.injectJs(plugin.js));
        }

        // load multiple css styling
        if (plugin.css && Array.isArray(plugin.css)) {
            allStyles = plugin.css.map(src => this.injectCss(src));
        } else if (plugin.css) {
            allStyles.push(this.injectCss(plugin.css));
        }

        try {
            return await Promise.all([...allScripts, ...allStyles]);
        } catch (_) {
            console.warn('Error while loading plugin', plugin);

            return null;
        }
    }

    /**
     * Inject js to end of body
     * @private
     * @param {String} scriptSrc
     * @returns {Promise<any[]>}
     */
    injectJs(scriptSrc) {
        return new Promise((resolve, reject) => {
            // create script tag with src
            const script = document.createElement('script');
            script.src = scriptSrc;
            script.async = true;

            // resolve when script was loaded succcessfully
            script.onload = () => {
                resolve();
            };

            // when script get not loaded successfully
            script.onerror = () => {
                reject();
            };

            // Append the script to the end of body
            document.body.appendChild(script);
        });
    }

    /**
     * Inject js to end of head
     * @private
     * @param {String} styleSrc
     * @returns {Promise<any[]>}
     */
    injectCss(styleSrc) {
        return new Promise((resolve, reject) => {
            // create style link with src
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = styleSrc;

            // resolve when script was loaded succcessfully
            link.onload = () => {
                resolve();
            };

            // when style get not loaded successfully
            link.onerror = () => {
                reject();
            };

            // Append the style to the end of head
            document.head.appendChild(link);
        });
    }
}

export default ApplicationBootstrapper;
