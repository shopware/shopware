/**
 * @sw-package framework
 *
 * Public TypeScript contract for code that runs through window.Shopware.
 * Keep this declaration structural and dependency-light: importing private
 * Administration implementation types would make every extension compile the
 * complete Administration source graph.
 */

import type Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
import type { DefineComponent } from 'vue';

declare global {
    type EntityName = keyof EntitySchema.Entities;

    interface ShopwareEntityState {
        id: string;
        getEntityName?(): string;
        getOrigin?(): Record<string, unknown>;
        isNew?(): boolean;
    }

    type Entity<Name extends EntityName> = EntitySchema.Entities[Name] & ShopwareEntityState;

    interface EntityCollection<Name extends EntityName> extends Array<Entity<Name>> {
        total: number;
        aggregations?: Record<string, unknown>;
        criteria?: Criteria;
        first(): Entity<Name> | null;
        get(id: string): Entity<Name> | null;
        has(id: string): boolean;
        last(): Entity<Name> | null;
    }

    interface ShopwareApiContext {
        apiPath?: string;
        apiResourcePath?: string;
        authToken?: {
            access: string;
            expiry: number;
            refresh: string;
        };
        languageId?: string;
        systemLanguageId?: string;
        inheritance?: boolean;
        liveVersionId?: string;
        versionId?: string;
    }

    interface ShopwareRepository<Name extends EntityName> {
        readonly entityName: Name;
        assign(id: string, context?: ShopwareApiContext): Promise<unknown>;
        clone(entityId: string, behavior: Record<string, unknown>, context?: ShopwareApiContext): Promise<unknown>;
        create(context?: ShopwareApiContext, id?: string | null): Entity<Name>;
        delete(id: string, context?: ShopwareApiContext): Promise<unknown>;
        discard(entity: Entity<Name>): void;
        get(id: string, context?: ShopwareApiContext, criteria?: Criteria | null): Promise<Entity<Name> | null>;
        hasChanges(entity: Entity<Name>): boolean;
        save(entity: Entity<Name>, context?: ShopwareApiContext): Promise<unknown>;
        saveAll(entities: EntityCollection<Name>, context?: ShopwareApiContext): Promise<unknown>;
        search(criteria: Criteria, context?: ShopwareApiContext): Promise<EntityCollection<Name>>;
        searchIds(criteria: Criteria, context?: ShopwareApiContext): Promise<{ total: number; data: string[] }>;
        sync(entities: EntityCollection<Name>, context?: ShopwareApiContext, failOnError?: boolean): Promise<unknown>;
    }

    interface ShopwareRepositoryFactory {
        create<Name extends EntityName>(
            entityName: Name,
            route?: string,
            options?: Record<string, unknown>,
        ): ShopwareRepository<Name>;
    }

    interface CustomShopwareServices {
        repositoryFactory: ShopwareRepositoryFactory;
    }

    interface CustomShopwareProperties {}

    interface ShopwareComponentApi {
        build(name: string): Promise<unknown>;
        extend(name: string, extendFrom: string, config: Record<string, unknown>): boolean;
        getComponentRegistry(): Map<string, unknown>;
        getOverrideComponents(): Array<DefineComponent<unknown, unknown, unknown>>;
        getTemplate(name: string): string | null;
        override(name: string, config: Record<string, unknown>): boolean;
        overrideComponentSetup(targetComponent: string, extension: (previousState: unknown) => unknown): void;
        register(name: string, config: Record<string, unknown>): boolean;
        registerOverrideComponent(component: DefineComponent<unknown, unknown, unknown>): void;
    }

    interface ShopwareModuleApi {
        getModuleByEntityName(entityName: string): unknown;
        getModuleRegistry(): Map<string, unknown>;
        getModuleRoutes(): unknown[];
        register(name: string, config: Record<string, unknown>): boolean;
    }

    interface ShopwareExtensionApi {
        ApiService: Record<string, unknown>;
        Application: Record<string, unknown>;
        Classes: Record<string, unknown>;
        Component: ShopwareComponentApi;
        Constants: Record<string, unknown>;
        Context: {
            api: ShopwareApiContext;
            app: Record<string, unknown>;
        };
        Data: {
            Criteria: typeof Criteria;
            [key: string]: unknown;
        };
        Defaults: {
            apiSalesChannelTypeId: string;
            defaultLanguageIds: string[];
            defaultSalutationId: string;
            productComparisonTypeId: string;
            storefrontSalesChannelTypeId: string;
            systemLanguageId: string;
            versionId: string;
            [key: string]: unknown;
        };
        Directive: Record<string, unknown>;
        EntityDefinition: Record<string, unknown>;
        ExtensionAPI: Record<string, unknown>;
        Feature: Record<string, unknown>;
        Filter: Record<string, unknown>;
        Helper: Record<string, unknown>;
        InAppPurchase: Record<string, unknown>;
        Locale: Record<string, unknown>;
        Mixin: Record<string, unknown>;
        Module: ShopwareModuleApi;
        Plugin: Record<string, unknown>;
        Service<Name extends keyof CustomShopwareServices>(name: Name): CustomShopwareServices[Name];
        Service(name: string): unknown;
        Shortcut: Record<string, unknown>;
        readonly Snippet: Record<string, unknown> | null;
        State: unknown;
        Store: Record<string, unknown>;
        Template: Record<string, unknown>;
        Utils: Record<string, unknown>;
        Vue: typeof import('vue');
        WorkerNotification: Record<string, unknown>;
    }

    const Shopware: ShopwareExtensionApi & CustomShopwareProperties;

    interface Window {
        Shopware: ShopwareExtensionApi & CustomShopwareProperties;
    }
}

export {};
