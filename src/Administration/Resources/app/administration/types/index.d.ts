/// <reference lib="dom" />

import { Component } from 'vue/types';
import { LocaleMessages } from 'vue-i18n';
import { Dispatch, Commit, Module } from 'vuex/types';
import { LoDashStatic } from 'lodash';

import { EntityDefinition } from './core/data/entity-definition.data';
import { ChangesetGenerator } from './core/data/changeset-generator.data';
import { Criteria } from './core/data/criteria.data.';
import { Entity } from './core/data/entity.data';
import { ApiService } from './core/service/api.service';
import { WorkerNotificationDefinition } from './core/worker/worker-notifcation-listener';
import { MiddlewareHelper } from './core/helper/middleware.data';
import { ShopwareError } from './core/data/ShopwareError';
import { FlatTreeHelper } from './core/helper/flattree.helper';
import { RefreshTokenHelper } from './core/helper/refresh-token.helper';
import { SanitizerHelper } from './core/helper/sanitizer.helper';
import { DeviceHelper } from './core/helper/device.helper';
import { EntityFactory } from './core/data/entity-factory.data';
import { EntityCollection } from './core/data/entity-collection.data';
import { EntityHydrator } from './core/data/entity-hydrator.data';
import { ErrorResolver } from './core/data/error-resolver.data';
import { ErrorStore } from './core/data/error-store.data';
import { FilterFactory } from './core/data/filter-factory.data';
import { Repository } from './core/data/repository.data';
import { Context } from './core/service/login.service';
import { ApplicationBootstrapper as Application } from './core/application';
import { Feature } from './core/feature';

declare namespace ShopwareNamespace {
    type ModuleType = 'core' | 'plugin';
    type SettingsItemGroup = 'shop' | 'system' | 'plugins';

    interface RouteMeta {
        parentPath?: string;
        privilege?: string;
        [other: string]: any;
    }

    interface Route {
        component: string;
        path: string;
        coreRoute?: boolean;
        meta?: RouteMeta;
        propsData?: object;
        alias?: string;
        name?: string;
        children?: Routes;
    }

    interface RouteMiddleware extends Route {
        type?: ModuleType;
        isChildren?: boolean;
        routeKey?: string;
    }

    interface Routes {
        [key: string]: Route;
    }

    interface Navigation {
        label: string;
        moduleType?: ModuleType;
        parent?: string;
        id?: string;
        path?: string;
        link?: string;
        position?: number;
        icon?: string;
        color?: string;
        privilege?: string;
    }

    interface SettingsItem {
        group: SettingsItemGroup;
        to: string;
        icon?: string;
        iconComponent?: string;
        id?: string;
        name?: string;
        label?: string;
        backgroundEnabled?: boolean;
        privilege?: string;
    }

    interface ExtensionEntryRoute {
        extensionName: string;
        route: string;
        label?: string;
    }

    interface ModuleDefinition {
        routes: Routes;
        name: string;
        title?: string;
        description?: string;
        version?: string;
        targetVersion?: string;
        color?: string;
        type?: ModuleType;
        icon?: string;
        favIcon?: string;
        entity?: string;
        routePrefixName?: string;
        navigation?: Navigation[];
        settingsItem?: SettingsItem | SettingsItem[];
        flag?: string;
        snippets?: LocaleMessages;
        routeMiddleware?: (
            next: () => void,
            currentRoute: RouteMiddleware
        ) => void;
        extensionEntryRoute?: ExtensionEntryRoute;
    }

    type PropertyBlacklist = [
        'createdAt',
        'updatedAt',
        'uploadedAt',
        'childCount',
        'versionId',
        'links',
        'extensions',
        'mimeType',
        'fileExtension',
        'metaData',
        'fileSize',
        'fileName',
        'mediaType',
        'mediaFolder'
    ];

    type ScalarTypes = [
        'uuid',
        'int',
        'text',
        'password',
        'float',
        'string',
        'blob',
        'boolean',
        'date'
    ];

    type JsonTypes = ['json_list', 'json_object'];

    interface Service {
        get(name: string): any | undefined;

        list(): string[];

        register(name: string, service: any): Application;

        registerMiddleware(...args: any): Application;

        registerDecorator(...args: any): Application;
    }

    function Service(name?: string): any | Service;

    interface Shopware {
        Module: {
            register(
                moduleId: string,
                module: ModuleDefinition
            ): {
                routes: Routes;
                manifest: ModuleDefinition;
                type: ModuleType;
            };

            getModuleRegistry(): Record<string, ModuleDefinition>;

            getModuleRoutes(): Routes;

            getModuleByEntityName(entityName: string): ModuleDefinition;
        };

        Component: {
            register(
                componentName: string,
                componentConfiguration: Component
            ): boolean | Component;

            extend(
                componentName: string,
                extendComponentName: string,
                componentConfiguration: Component
            ): Component;

            override(
                componentName: string,
                componentConfiguration: Component,
                overrideIndex?: number
            ): Component;

            build(componentName: string, skipTemplate?: boolean): Component;

            getTemplate(componentName: string): string;

            getComponentRegistry(): Record<string, Component>;

            getComponentHelper(): Record<string, () => any>;

            registerComponentHelper(
                name: string,
                helperFunction: () => any
            ): boolean;
        };

        Template: {
            register(
                componentName: string,
                componentTemplate?: string | null
            ): boolean;

            extend(
                componentName: string,
                extendComponentName: string,
                templateExtension?: string | null
            ): boolean;

            override(
                componentName: string,
                templateOverride?: string | null,
                overrideIndex?: number
            ): boolean;

            getRenderedTemplate(componentName: string): string | null;
        };

        Filter: {
            register(
                filterName: string,
                filterFactoryMethod: (value: any) => any
            ): boolean;

            getByName(filterName: string): (value: any) => object | undefined;

            getRegistry(): Record<string, (value: any) => any>;
        };

        Mixin: {
            register(
                mixinName: string,
                mixin: Component
            ): boolean | Vue.Component;

            getByName(mixinName: string): Component;
        };

        Locale: {
            register(
                localeName: string,
                localeMessages: LocaleMessages
            ): boolean | string;

            extend(
                localeName: string,
                localeMessages: LocaleMessages
            ): boolean | string;

            getByName(localeName: string): void;

            getLocaleRegistry(): boolean | LocaleMessages;
        };

        Plugin: {
            addBootPromise(): Promise<void>;

            getBootPromises(): Array<Promise<void>>;
        };

        Shortcut: {
            getShortcutRegistry(
                combination: string,
                path?: string
            ): string | boolean;

            getPathByCombination(combination: string): string | boolean;

            register(combination: string, path?: string): string | boolean;
        };

        ApiService: {
            register(
                apiServiceName: string,
                apiService: ApiService | null
            ): boolean;

            getByName(apiServiceName: string): ApiService | undefined;

            getRegistry(): Record<string, ApiService>;

            getServices(): [string, ApiService];

            has(apiServiceName: string): boolean;
        };

        Entity: {
            addDefinition(entityName: string, entityDefinition: any): boolean;

            getDefinition(entityName: string): any;

            getDefinitionRegistry(): Record<string, any>;

            getRawEntityObject(schema: any, deep?: boolean): any;

            getPropertyBlacklist(): PropertyBlacklist;

            getRequiredProperties(entityName: string): string[];

            getAssociatedProperties(entityName: string): string[];

            getTranslatableProperties(entityName: string): void;
        };

        EntityDefinition: {
            getScalarTypes(): ScalarTypes;

            getJsonTypes(): JsonTypes;

            getDefinitionRegistry(): Record<string, EntityDefinition>;

            has(entityName: string): boolean;

            get(entityName: string): EntityDefinition;

            add(entityName: string, schema: object): void;

            remove(entityName: string): boolean;

            getTranslatedFields(entityName: string): object;

            getAssociationFields(entityName: string): object;

            getRequiredFields(entityName: string): object;
        };

        WorkerNotification: {
            register(name: string, opts: WorkerNotificationDefinition): boolean;

            getRegistry(): Record<string, WorkerNotificationDefinition>;

            override(name: string, opts: WorkerNotificationDefinition): boolean;

            remove(name: string): boolean;

            initialize(): MiddlewareHelper;
        };

        Classes: {
            ShopwareError: ShopwareError;

            ApiService: ApiService;
        };

        Helper: {
            FlatTreeHelper: FlatTreeHelper;

            MiddlewareHelper: MiddlewareHelper;

            RefreshTokenHelper: RefreshTokenHelper;

            SanitizerHelper: SanitizerHelper;

            DeviceHelper: DeviceHelper;
        };

        Defaults: {
            systemLanguageId: string;

            defaultLanguageIds: string[];

            versionId: string;

            storefrontSalesChannelTypeId: string;

            productComparisonTypeId: string;

            apiSalesChannelTypeId: string;
        };

        State: {
            list(): string[];

            get(name: string): undefined | Module<string, any>;

            getters(): any;

            commit: Commit;

            dispatch: Dispatch;

            watch(...args: any[]): void;

            subscribe(...args: any[]): void;

            subscribeAction(...args: any[]): void;

            registerModule(...args: any[]): void;

            unregisterModule(...args: any[]): void;
        };

        Data: {
            ChangesetGenerator: ChangesetGenerator;

            Criteria: Criteria;

            Entity: Entity;

            EntityCollection: EntityCollection;

            EntityDefinition: EntityDefinition;

            EntityFactory: EntityFactory;

            EntityHydrator: EntityHydrator;

            ErrorResolver: ErrorResolver;

            ErrorStore: ErrorStore;

            FilterFactory: FilterFactory;

            Repository: Repository;
        };

        Utils: {
            array: {
                flattenDeep: LoDashStatic['flattenDeep'];

                remove: LoDashStatic['remove'];

                slice: LoDashStatic['slice'];

                uniqBy: LoDashStatic['uniqBy'];
            };
            createId(): string;

            debounce: LoDashStatic['debounce'];

            debug: {
                error(...args: any[]): void;

                warn(...args: any[]): void;
            };

            dom: {
                getScrollbarHeight(element: HTMLElement): number;

                getScrollbarWidth(element: HTMLElement): number;

                copyToClipboard(stringToCopy: string): void;
            };

            fileReader: {
                readAsArrayBuffer(
                    inputFile: File
                ): Promise<FileReader['result']>;

                readAsDataURL(inputFile: File): Promise<FileReader['result']>;

                readAsText(inputFile: File): Promise<FileReader['result']>;

                getNameAndExtensionFromFile(
                    fileHandle: File
                ): {
                    extension: string;
                    fileName: string;
                };

                getNameAndExtensionFromUrl(
                    urlObject: URL
                ): {
                    extension: string;
                    fileName: string;
                };
            };

            flow: LoDashStatic['flow'];

            format: {
                currency(
                    val: string,
                    sign: string,
                    decimalPlaces: number,
                    additionalOptions: Intl.NumberFormatOptions
                ): string;

                date(val: Date, options: Intl.DateTimeFormatOptions): string;

                md5(value: string): string;

                fileSize(bytes: number, locale?: string): string;
            };

            get: LoDashStatic['get'];

            object: {
                cloneDeep: LoDashStatic['cloneDeep'];

                deepCopyObject(copyObject?: object): object;

                deepMergeObject(
                    firstObject?: object,
                    secondObject?: object
                ): object;

                get: LoDashStatic['get'];

                getArrayChanges(a: any[], b: any[]): any[];

                getObjectDiff(a: object, b: object): object;

                hasOwnProperty(scope: object, prop: string): boolean;

                merge: LoDashStatic['merge'];

                mergeWith: LoDashStatic['mergeWith'];

                pick: LoDashStatic['pick'];

                set: LoDashStatic['set'];
            };

            sort: {
                afterSort(elements: object[], property?: string): any[];
            };

            string: {
                camelCase: LoDashStatic['camelCase'];

                capitalizeString: LoDashStatic['capitalize'];

                isEmptyOrSpaces(value: string): boolean;

                isUrl(value: string): boolean;

                isValidIp(value: string): boolean;

                kebabCase: LoDashStatic['kebabCase'];

                md5(value: string): string;

                snakeCase: LoDashStatic['snakeCase'];
            };

            throttle: LoDashStatic['throttle'];

            types: {
                isArray: LoDashStatic['isArray'];

                isBoolean: LoDashStatic['isBoolean'];

                isDate: LoDashStatic['isDate'];

                isEmpty: LoDashStatic['isEmpty'];

                isEqual: LoDashStatic['isEqual'];

                isFunction: LoDashStatic['isFunction'];

                isNumber: LoDashStatic['isNumber'];

                isObject: LoDashStatic['isObject'];

                isPlainObject: LoDashStatic['isPlainObject'];

                isRegExp: LoDashStatic['isRegExp'];

                isString: LoDashStatic['isString'];

                isUndefined(value: any): boolean;
            };
        };

        Application: Application;

        Context: Context;

        Feature: Feature;
    }
}

declare global {
    interface Window {
        Shopware: ShopwareNamespace.Shopware;
    }
}

declare module NodeJS {
    interface Global {
        Shopware: ShopwareNamespace.Shopware;
    }
}

declare var Shopware: ShopwareNamespace.Shopware;
export = Shopware;
export as namespace Shopware;
