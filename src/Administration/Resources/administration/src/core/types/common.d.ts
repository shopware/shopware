// Type definition for Shopware Exposed Application
// Project: Showpare Next

import Bottle from "bottlejs";
import Vue from "vue";
import { ComponentOptions } from "vue/types";
import Application = Shopware.Application;

type FactoryMethod = ((this: Application, container: Bottle.IContainer) => object);
type DecoratorMethod = FactoryMethod;

declare namespace Shopware {
    export namespace Module {
        export function register(
            moduleName: string,
            declaration: {
                type: string,
                name?: string,
                description?: string,
                version?: string,
                targetVersion?: string,
                color?: string,
                icon?: string,
                routes: {[key: string]: {
                    component?: string,
                    components?: {[key: string]: Array<any>},
                    path: string,
                    meta?: {[key: string]: any},
                    alias?: string,
                    redirect?: string
                }},
                navigation?: Array<{
                    path: string,
                    label: string,
                    color?: string,
                    icon?: string,
                    parent?: string
                }>,
                commands?: Array<object>,
                shortcuts: object
            }
        ): boolean|object;
    }

    export namespace Component {
        export function register(
            componentName: string,
            declaration: ComponentOptions<Vue>
        ): boolean|object;

        export function extend(
            componentName: string,
            extendComponentName: string,
            componentConfiguration: ComponentOptions<Vue>
        ): object;

        export function override(
            componentName: string,
            componentConfiguration: ComponentOptions<Vue>,
            overrideIndex?: number = null
        ): object;

        export function build(
            componentName: string,
            skipTemplate?: boolean = false
        ): object;

        export function getTemplate(
            componentName: string
        ): string;
    }

    export namespace Template {
        export function register(
            componentName: string,
            componentTemplate?: string = null
        ): boolean;

        export function extend(
            componentName: string,
            extendComponentName: string,
            templateExtension?: string = null
        ): void;

        export function override(
            componentName: string,
            templateOverride?: string = null,
            overrideIndex?: number = null
        ): void;

        export function getRenderedTemplate(
            componentName: string,
        ): string;

        export function find(
            componentName: string
        ): string;

        export function findOverride(
            componentName: string
        ): string;
    }

    export namespace Utils {
        export function merge(
            target: object,
            source: object
        ): object;

        export function formDataToObject(
            formData: FormData
        ): object;

        export function warn(
            name?: string = 'Core',
            message?: any
        ): void;

        export function currency(
            val: number,
            sign?: string = 'EUR'
        ): string;

        export function date(
            val: string,
            locale?: string = 'de-DE'
        ) : string;

        export function isObject(
            object: any
        ): boolean;

        export function isPlainObject(
            obj: any
        ): boolean;

        export function isEmpty(
            object: object
        ): boolean;

        export function isRegExp(
            exp: any
        ): boolean;

        export function isArray(
            array: any
        ): boolean;

        export function isFunction(
            func: any
        ): boolean;

        export function isDate(
            dateObject: any
        ): boolean;

        export function getObjectChangeSet(
            baseObject: object,
            compareObject: object
        ): object;

        export function getArrayChangeSet(
            baseArray: Array<any>,
            compareArray: Array<any>,
        ): Array<any>;
    }

    export namespace Application {
        export function getContainer(
            containerName?: string
        ): Bottle.IContainer;

        export function addFactory(
            name: string,
            factory: FactoryMethod
        ): Application;

        export function addFactoryMiddleware(
            factory: FactoryMethod
        ): Application;

        export function addFactoryMiddleware(
            containerName: string,
            factory: FactoryMethod
        ): Application;

        export function addFactoryDecorator(
            factory: DecoratorMethod
        ): Application;

        export function addFactoryDecorator(
            containerName: string,
            factory: DecoratorMethod
        ): Application;

        export function addInitializer(
            name: string,
            initializer: FactoryMethod
        ): Application;

        export function addServiceProvider(
            name: string,
            provider: FactoryMethod
        ): Application;

        export function registerContext(
            context: object
        ): Application;

        export function addInitializerMiddleware(
            factory: FactoryMethod
        ): Application;

        export function addInitializerMiddleware(
            containerName: string,
            factory: FactoryMethod
        ): Application;

        export function addServiceProviderMiddleware(
            factory: FactoryMethod
        ): Application;

        export function addServiceProviderMiddleware(
            containerName: string,
            factory: FactoryMethod
        ): Application;

        export function _addMiddleware(
            containerName: string,
            args: Array<any>
        ): Application;

        export function addInitializerDecorator(
            factory: DecoratorMethod
        ): Application;

        export function addInitializerDecorator(
            containerName: string,
            factory: DecoratorMethod
        ): Application;

        export function addServiceProviderDecorator(
            factory: DecoratorMethod
        ): Application;

        export function addServiceProviderDecorator(
            containerName: string,
            factory: DecoratorMethod
        ): Application;

        export function _addDecorator(
            containerName: string,
            args: Array<any>
        ): Application;

        export function start(
            context: object
        ): void;

        export function getApplicationRoot(): boolean | Vue;
    }

    export namespace State {
        export function register(
            namespacePath: string,
            moduleDefinition: object
        ): boolean
    }
}
