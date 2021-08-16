import Bottle from 'bottlejs';
import Vue from 'vue';
import { Context } from './service/login.service';

type FactoryMethod = (container: Bottle.IContainer, next: () => void) => void;

interface AppContext {
    features: {
        [key: string]: boolean;
    };
    firstRunWizard: boolean;
    systemCurrencyISOCode: string;
    systemCurrencyId: string;
}

export class ApplicationBootstrapper {
    constructor(container: Bottle);

    getContainer(containerName: string): Bottle.IContainer;

    addFactory(
        name: string,
        factory: (container: Bottle.IContainer) => any
    ): ApplicationBootstrapper;

    addFactoryMiddleware(
        factoryName: string,
        factory: FactoryMethod
    ): ApplicationBootstrapper;

    addFactoryMiddleware(factory: FactoryMethod): ApplicationBootstrapper;

    addFactoryDecorator(
        factoryName: string,
        factory: FactoryMethod
    ): ApplicationBootstrapper;

    addFactoryDecorator(factory: FactoryMethod): ApplicationBootstrapper;

    addInitializer(
        name: string,
        factory: (container: Bottle.IContainer) => any
    ): ApplicationBootstrapper;

    addServiceProvider(
        name: string,
        factory: (container: Bottle.IContainer) => any
    ): ApplicationBootstrapper;

    registerConfig(config: {
        apiContext: Context;
        appContext: AppContext;
    }): ApplicationBootstrapper;

    registerApiContext(context: Context): ApplicationBootstrapper;

    registerAppContext(context: AppContext): ApplicationBootstrapper;

    addServiceProviderMiddleware(
        factoryName: string,
        factory: FactoryMethod
    ): ApplicationBootstrapper;

    addServiceProviderMiddleware(
        factory: FactoryMethod
    ): ApplicationBootstrapper;

    private _addMiddleware(
        containerName: string,
        args: any[]
    ): ApplicationBootstrapper;

    initializeFeatureFlags(): ApplicationBootstrapper;

    addServiceProviderDecorator(
        factoryName: string,
        factory: FactoryMethod
    ): ApplicationBootstrapper;

    addServiceProviderDecorator(
        factory: FactoryMethod
    ): ApplicationBootstrapper;

    _addDecorator(containerName: string, args: any[]): ApplicationBootstrapper;

    start(config?: {
        apiContext: Context;
        appContext: AppContext;
    }): ApplicationBootstrapper;

    initState(): ApplicationBootstrapper;

    getApplicationRoot(): boolean | Vue;

    setViewAdapter(viewAdapterInstance: any): void;

    startBootProcess(): Promise<ApplicationBootstrapper>;

    bootLogin(): Promise<ApplicationBootstrapper>;

    bootFullApplication(): Promise<ApplicationBootstrapper>;

    createApplicationRoot(): Promise<ApplicationBootstrapper>;

    createApplicationRootError(error: any): void;

    initializeInitializers(container: Bottle, prefix?: string): Promise<any[]>;

    initializeLoginInitializer(
        container: Bottle,
        prefix?: string
    ): Promise<any[]>;

    getAsyncInitializers(initializer: Bottle.IContainer): any[];

    loadPlugins(): Promise<never[]>;

    loadPlugin(plugin: { css: string; js: string }): Promise<never[]>;

    injectJs(scriptSrc: string): Promise<void>;

    injectCss(styleSrc: string): Promise<void>;
}
