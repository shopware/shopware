/* global Shopware */

/**
 * The application bootstrapper bootstraps the application and registers the necessary
 * and optional parts of the application in a shared DI container which provides you
 * with an easy-to-use way to add new services as well as decoration these services.
 *
 * The bootstrapper provides you with the ability to register middleware for all or specific
 * services too.
 *
 * @class
 */
export default class ApplicationBootstrapper {
    /**
     * Provides the necessary class properties for the class to work probably
     *
     * @constructor
     * @param {Bottle} container
     */
    constructor(container) {
        const noop = () => {};
        this.$container = container;

        // Create an empty DI container for the core initializers & services, so we can separate the core initializers
        // and the providers
        this.$container.service('service', noop);
        this.$container.service('init', noop);
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
     * Adds an initializer to the application. An initializer is a necessary part of the application which needs to be
     * initialized before we can boot up the application.
     *
     * The initializer will be registered in a nested DI container.
     *
     * @example
     * Shopware.Application.addInitializer('httpClient', (container) => {
     *    return HttpFactory(container.contextService);
     * });
     *
     * @param {String} name Name of the initializer
     * @param {Function} initializer Factory method
     * @returns {ApplicationBootstrapper}
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
     * @returns {ApplicationBootstrapper}
     */
    addServiceProvider(name, provider) {
        this.$container.factory(`service.${name}`, provider.bind(this));
        return this;
    }

    /**
     * Registers the application context (api path, path to resources etc.)
     *
     * @param {Object} context
     * @returns {ApplicationBootstrapper}
     */
    registerContext(context) {
        return this.addInitializer('context', () => {
            return context;
        });
    }

    /**
     * Registers an initializer middleware for either every initializer in the container or a defined one.
     *
     * @example
     * Shopware.Application.addInitializerMiddleware((container, next) => {
     *    // Do something with the container
     *    next();
     * });
     *
     * @example
     * Shopware.Application.addInitializerMiddleware('httpClient', (service, next) => {
     *    // Do something with the service
     *    next();
     * });
     *
     * @param args
     * @returns {ApplicationBootstrapper}
     */
    addInitializerMiddleware(...args) {
        return this._addMiddleware('init', args);
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
     * @returns {ApplicationBootstrapper}
     */
    addServiceProviderMiddleware(...args) {
        return this._addMiddleware('service', args);
    }

    /**
     * Helper method which registers a middleware
     *
     * @param {String} containerName
     * @param {Array} args
     * @returns {ApplicationBootstrapper}
     * @private
     */
    _addMiddleware(containerName, args) {
        const name = (args.length > 1 ? `${containerName}.${args[0]}` : containerName);
        const middlewareFn = (args.length > 1 ? args[1] : args[0]);

        this.$container.middleware(name, middlewareFn);

        return this;
    }

    /**
     * Registers a decorator for either every initializer in the container or a defined one.
     *
     * @example
     * Shopware.Application.addInitializerDecorator((container, next) => {
     *    // Do something with the container
     *    next();
     * });
     *
     * @example
     * Shopware.Application.addInitializerDecorator('httpClient', (service, next) => {
     *    // Do something with the service
     *    next();
     * });
     *
     * @param args
     * @returns {ApplicationBootstrapper}
     */
    addInitializerDecorator(...args) {
        return this._addDecorator('init', args);
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
     * @returns {ApplicationBootstrapper}
     */
    addServiceProviderDecorator(...args) {
        return this._addDecorator('service', args);
    }

    /**
     * Helper method which registers a decorator
     *
     * @param {String} containerName
     * @param {Array} args
     * @returns {ApplicationBootstrapper}
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
     * @param context
     */
    start(context = {}) {
        this.registerContext(context)
            .createApplicationRoot();
    }

    /**
     * Returns the root of the application e.g. a new Vue instance
     *
     * @returns {Boolean|Vue}
     */
    getApplicationRoot() {
        if (!this.applicationRoot) {
            return false;
        }

        return this.applicationRoot;
    }

    /**
     * Creates the application root and injects the provider container into the
     * view instance to keep the dependency injection of Vue.js in place.
     *
     * @returns {ApplicationBootstrapper}
     */
    createApplicationRoot() {
        const container = this.getContainer('init');
        const router = container.router;
        const view = container.view;

        this.applicationRoot = view.createInstance(
            '#app',
            router,
            this.getContainer('service')
        );

        return this;
    }
}
