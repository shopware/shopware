import type Bottle from 'bottlejs';
import Vue from 'vue';
import type { ContextState } from '../app/state/context.store';
import type VueAdapter from '../app/adapter/view/vue.adapter';
/**
 * @package admin
 *
 * @module core/application
 */

interface bundlesSinglePluginResponse {
    css?: string | string[],
    js?: string | string[],
    html?: string,
    baseUrl?: null | string,
    type?: 'app'|'plugin',
    version?: string,
    // Properties below this line are only available for apps
    integrationId?: string,
    active?: boolean,
}

interface bundlesPluginResponse {
    [key: string]: bundlesSinglePluginResponse
}

/**
 * @deprecated tag:v6.6.0 - Will be private
 *
 * The application bootstrapper bootstraps the application and registers the necessary
 * and optional parts of the application in a shared DI container which provides you
 * with an easy-to-use way to add new services as well as decoration these services.
 *
 * The bootstrapper provides you with the ability to register middleware for all or specific
 * services too.
 */
class ApplicationBootstrapper {
    public $container: Bottle;

    public view: null | VueAdapter;

    /**
     * Provides the necessary class properties for the class to work probably
     */
    constructor(container: Bottle) {
        // eslint-disable-next-line @typescript-eslint/no-empty-function
        const noop = (): void => {};
        this.$container = container;

        this.view = null;

        // Create an empty DI container for the core initializers & services, so we can separate the core initializers
        // and the providers
        this.$container.service('service', noop);
        this.$container.service('init', noop);
        this.$container.service('factory', noop);
    }

    /**
     * Returns all containers. Use this method if you want to get initializers in your services.
     */
    getContainer<T extends Bottle.IContainerChildren>(containerName: T): Bottle.IContainer[T] {
        if (typeof containerName === 'string' && this.$container.container[containerName]) {
            return this.$container.container[containerName];
        }

        // @ts-expect-error
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
     */
    addFactory<T extends keyof FactoryContainer>(
        name: T,
        factory: (container: Bottle.IContainer) => FactoryContainer[T],
    ): ApplicationBootstrapper {
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
     */
    addFactoryMiddleware<SERVICE extends keyof Bottle.IContainer['factory']>(
        nameOrMiddleware: SERVICE|Bottle.Middleware,
        middleware? : Bottle.Middleware,
    ): ApplicationBootstrapper {
        return this._addMiddleware('factory', nameOrMiddleware, middleware);
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
     */
    addFactoryDecorator(
        nameOrDecorator: keyof FactoryContainer|Bottle.Decorator,
        decorator? : Bottle.Decorator,
    ): ApplicationBootstrapper {
        return this._addDecorator('factory', nameOrDecorator, decorator);
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
     */
    addInitializer<I extends keyof InitContainer>(name: I, initializer: () => InitContainer[I]): ApplicationBootstrapper {
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
     */
    addServiceProvider<S extends keyof ServiceContainer>(
        name: S,
        provider: (serviceContainer: ServiceContainer) => ServiceContainer[S],
    ): ApplicationBootstrapper {
        // @ts-expect-error
        this.$container.factory(`service.${name}`, provider.bind(this));
        return this;
    }

    registerConfig(config: { apiContext?: ContextState['api'], appContext?: ContextState['app'] }): ApplicationBootstrapper {
        if (config.apiContext) {
            this.registerApiContext(config.apiContext);
        }
        if (config.appContext) {
            this.registerAppContext(config.appContext);
        }

        return this;
    }

    /**
     * Registers the api context (api path, path to resources etc.)
     */
    registerApiContext(context: ContextState['api']): ApplicationBootstrapper {
        Shopware.Context.api = Shopware.Classes._private.ApiContextFactory(context);

        return this;
    }

    /**
     * Registers the app context (firstRunWizard, etc.)
     */
    registerAppContext(context: ContextState['app']): ApplicationBootstrapper {
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
     */
    addServiceProviderMiddleware<SERVICE extends keyof ServiceContainer>(
        nameOrMiddleware: SERVICE|Bottle.Middleware,
        middleware? : ((service: ServiceContainer[SERVICE], next: (error?: Error) => void) => void),
    ): ApplicationBootstrapper {
        return this._addMiddleware('service', nameOrMiddleware, middleware);
    }

    /**
     * Helper method which registers a middleware
     */
    private _addMiddleware<CONTAINER extends Bottle.IContainerChildren>(
        containerName: CONTAINER,
        nameOrMiddleware: keyof Bottle.IContainer[CONTAINER]|Bottle.Middleware,
        middleware? : Bottle.Middleware,
    ): ApplicationBootstrapper {
        if (typeof nameOrMiddleware === 'string' && !!middleware) {
            this.$container.middleware(`${containerName}.${nameOrMiddleware}`, middleware);
        }

        if (typeof nameOrMiddleware === 'function' && !!nameOrMiddleware) {
            this.$container.middleware(containerName, nameOrMiddleware);
        }

        return this;
    }

    /**
     * Initializes the feature flags from context settings
     */
    initializeFeatureFlags(): ApplicationBootstrapper {
        const features = Shopware.Context.app.features;

        if (!features) {
            throw new Error(`
                Feature initialization does not work
                because the context does not contain any features.
            `);
        }

        Shopware.Feature.init(features);

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
     */
    addServiceProviderDecorator(
        nameOrDecorator: keyof ServiceContainer|Bottle.Decorator,
        decorator? : Bottle.Decorator,
    ): ApplicationBootstrapper {
        return this._addDecorator('service', nameOrDecorator, decorator);
    }

    /**
     * Helper method which registers a decorator
     */
    _addDecorator<CONTAINER extends Bottle.IContainerChildren>(
        containerName: CONTAINER,
        nameOrDecorator: keyof Bottle.IContainer[CONTAINER]|Bottle.Decorator,
        decorator? : Bottle.Decorator,
    ): ApplicationBootstrapper {
        if (typeof nameOrDecorator === 'string' && !!decorator) {
            this.$container.decorator(`${containerName}.${nameOrDecorator}`, decorator);
        }

        if (typeof nameOrDecorator === 'function' && !!nameOrDecorator) {
            this.$container.decorator(containerName, nameOrDecorator);
        }

        return this;
    }

    /**
     * Starts the bootstrapping process of the application.
     */
    start(config = {}): Promise<void|ApplicationBootstrapper> {
        return this.initState()
            .registerConfig(config)
            .initializeFeatureFlags()
            .startBootProcess();
    }

    /**
     * Get the global state
     */
    initState(): ApplicationBootstrapper {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        const initaliziation = this.getContainer('init').state;

        if (initaliziation) {
            return this;
        }

        throw new Error('State could not be initialized');
    }

    /**
     * Returns the root of the application e.g. a new Vue instance
     */
    getApplicationRoot(): Vue | false {
        if (!this.view?.root) {
            return false;
        }

        return this.view.root;
    }

    setViewAdapter(viewAdapterInstance: VueAdapter): void {
        this.view = viewAdapterInstance;
    }

    /**
     * Boot the application depending on login status
     */
    startBootProcess(): Promise<void|ApplicationBootstrapper> {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        const loginService = this.getContainer('service').loginService;
        // eslint-disable-next-line max-len
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
        const isUserLoggedIn = loginService.isLoggedIn();

        // if user is not logged in
        if (!isUserLoggedIn) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            loginService.logout(false, false);
            return this.bootLogin();
        }

        return this.bootFullApplication();
    }

    /**
     * Boot the login.
     */
    bootLogin(): Promise<void|ApplicationBootstrapper> {
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
            .then(() => {
                if (!this.view) {
                    return Promise.reject();
                }

                return this.view.initDependencies();
            })
            .then(() => this.createApplicationRoot())
            .catch((error) => this.createApplicationRootError(error));
    }

    /**
     * Boot the whole application.
     */
    bootFullApplication(): Promise<void | ApplicationBootstrapper> {
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
            .then(() => {
                if (!this.view) { return Promise.reject(); }

                return this.view.initDependencies();
            })
            .then(() => this.createApplicationRoot())
            .catch((error) => this.createApplicationRootError(error));
    }

    /**
     * Creates the application root and injects the provider container into the
     * view instance to keep the dependency injection of Vue.js in place.
     */
    createApplicationRoot(): Promise<ApplicationBootstrapper> {
        const initContainer = this.getContainer('init');
        // eslint-disable-next-line max-len
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        const router = initContainer.router.getRouterInstance();

        // We're in a test environment, we're not needing an application root
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        if (Shopware.Context.app.environment === 'testing') {
            return Promise.resolve(this);
        }

        if (!this.view) {
            return Promise.reject(new Error('The ViewAdapter was not defined in the application.'));
        }

        this.view.init(
            '#app',
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            router,
            // @ts-expect-error
            this.getContainer('service'),
        );

        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        const firstRunWizard = Shopware.Context.app.firstRunWizard;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        if (firstRunWizard && !router?.history?.current?.name?.startsWith('sw.first.run.wizard.')) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            router.push({
                name: 'sw.first.run.wizard.index',
            });
        }

        if (typeof this._resolveViewInitialized === 'function') {
            this._resolveViewInitialized();
        }

        return Promise.resolve(this);
    }

    _resolveViewInitialized: undefined | ((arg0?: unknown) => void);

    /**
     * You can use this Promise to do things after the view
     * was initialized.
     */
    viewInitialized = new Promise((resolve) => {
        this._resolveViewInitialized = resolve;
    });

    /**
     * Creates the application root and show the error message.
     */
    createApplicationRootError(error: unknown): void {
        console.error(error);
        const container = this.getContainer('init');
        // eslint-disable-next-line max-len
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        const router = container.router.getRouterInstance();

        this.view?.init(
            '#app',
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            router,
            // @ts-expect-error
            this.getContainer('service'),
        );

        // @ts-expect-error
        if (this.view?.root?.initError) {
            // @ts-expect-error
            this.view.root.initError = error;
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        router.push({
            name: 'error',
        });
    }

    /**
     * Initialize the initializers right away cause these are the mandatory services for the application
     * to boot successfully.
     */
    private initializeInitializers(container: InitContainer, prefix = 'init'): Promise<unknown[]> {
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
     */
    private initializeLoginInitializer(): Promise<unknown[]> {
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

    getAsyncInitializers(initializer: InitContainer | string[]): unknown[] {
        const initContainer = this.getContainer('init');
        const asyncInitializers: unknown[] = [];

        Object.keys(initializer).forEach((serviceKey) => {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const service = initContainer[serviceKey];

            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (service?.constructor?.name === 'Promise') {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                asyncInitializers.push(service);
            }
        });

        return asyncInitializers;
    }

    /**
     * Load all plugins from the server and inject them into the Site.
     */
    private async loadPlugins():Promise<(unknown[] | null)[]> {
        const isDevelopmentMode = process.env.NODE_ENV;

        let plugins: bundlesPluginResponse;
        // only in webpack dev mode
        if (isDevelopmentMode === 'development') {
            const response = await fetch('./sw-plugin-dev.json');
            plugins = await response.json() as bundlesPluginResponse;
        } else {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            plugins = Shopware.Context.app.config.bundles as bundlesPluginResponse;
        }

        // prioritize main swag-commercial plugin because other plugins depend on the license handling
        if (plugins['swag-commercial']) {
            await this.injectPlugin(plugins['swag-commercial']);
        }

        const injectAllPlugins = Object.entries(plugins).filter(([pluginName]) => {
            // Filter the swag-commercial plugin because it was loaded beforehand
            return pluginName !== 'swag-commercial';
        }).map(([, plugin]) => this.injectPlugin(plugin));

        // inject iFrames of plugins
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        const bundles = Shopware.Context.app.config.bundles as bundlesPluginResponse;
        Object.entries(bundles).forEach(([bundleName, bundle]) => {
            if (!bundle.baseUrl) {
                return;
            }

            if (isDevelopmentMode === 'development') {
                // replace the baseUrl with the webpack url of the html file
                Object.entries(plugins).forEach(([pluginName, entryFiles]) => {
                    const stringUtils = Shopware.Utils.string;
                    const camelCasePluginName = stringUtils.upperFirst(stringUtils.camelCase(pluginName));

                    if (bundleName === camelCasePluginName && !!entryFiles.html) {
                        bundle.baseUrl = entryFiles.html;
                    }

                    // add origin if not set yet
                    if (bundle.baseUrl) {
                        bundle.baseUrl = (new URL(bundle.baseUrl, window.origin)).toString();
                    }
                });
            }

            this.injectIframe({
                active: bundle.active,
                integrationId: bundle.integrationId,
                bundleName,
                bundleVersion: bundle.version,
                iframeSrc: bundle.baseUrl,
                bundleType: bundle.type,
            });
        });

        if (isDevelopmentMode === 'development') {
            // inject iFrames of plugins which aren't detected yet from the config (no files in public folder)
            Object.entries(plugins).forEach(([pluginName, entryFiles]) => {
                const stringUtils = Shopware.Utils.string;
                const camelCasePluginName = stringUtils.upperFirst(stringUtils.camelCase(pluginName));

                if (Object.keys(bundles).includes(camelCasePluginName) || !entryFiles.html) {
                    return;
                }

                this.injectIframe({
                    bundleVersion: undefined,
                    bundleName: camelCasePluginName,
                    iframeSrc: entryFiles.html,
                });
            });
        }

        return Promise.all(injectAllPlugins);
    }

    /**
     * Inject plugin scripts and styles
     */
    private async injectPlugin(plugin: bundlesSinglePluginResponse): Promise<unknown[] | null> {
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
     */
    private injectJs(scriptSrc: string): Promise<void> {
        return new Promise<void>((resolve, reject) => {
            // create script tag with src
            const script = document.createElement('script');
            script.src = scriptSrc;
            script.async = true;

            // resolve when script was loaded succcessfully
            script.onload = ():void => {
                resolve();
            };

            // when script get not loaded successfully
            script.onerror = ():void => {
                reject();
            };

            // Append the script to the end of body
            document.body.appendChild(script);
        });
    }

    /**
     * Inject js to end of head
     */
    private injectCss(styleSrc: string): Promise<void> {
        return new Promise<void>((resolve, reject) => {
            // create style link with src
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = styleSrc;

            // resolve when script was loaded succcessfully
            link.onload = ():void => {
                resolve();
            };

            // when style get not loaded successfully
            link.onerror = ():void => {
                reject();
            };

            // Append the style to the end of head
            document.head.appendChild(link);
        });
    }

    /**
     * Inject hidden iframes
     */
    private injectIframe({
        active,
        integrationId,
        bundleName,
        iframeSrc,
        bundleVersion,
        bundleType,
    }: {
        active?: boolean,
        integrationId?: string,
        bundleName: string,
        iframeSrc: string,
        bundleVersion?: string,
        bundleType?: 'app'|'plugin',
    }): void {
        const bundles = Shopware.Context.app.config.bundles;
        let permissions = null;

        if (bundles && bundles.hasOwnProperty(bundleName)) {
            permissions = bundles[bundleName].permissions;
        }

        const extension = {
            active,
            integrationId,
            name: bundleName,
            baseUrl: iframeSrc,
            version: bundleVersion,
            type: bundleType,
        };

        // To keep permissions reactive no matter if empty or not
        Vue.set(extension, 'permissions', permissions ?? Vue.observable({}));

        Shopware.State.commit('extensions/addExtension', extension);
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ApplicationBootstrapper;
