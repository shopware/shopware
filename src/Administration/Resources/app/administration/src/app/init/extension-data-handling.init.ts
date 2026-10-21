/* eslint-disable @typescript-eslint/prefer-promise-reject-errors */
/**
 * @sw-package framework
 */

import type Repository from '../../core/data/repository.data';
import type { ContextState } from '../composables/use-context';

function getRepository<EntityName extends keyof EntitySchema.EntityKeys>(
    entityName: EntityName,
    additionalInformation: { _event_: MessageEvent<string> },
): Repository<EntityName> | null {
    const extensionName = Object.keys(Shopware.Store.get('extensions').extensionsState).find((key) =>
        Shopware.Store.get('extensions').extensionsState[key].baseUrl.startsWith(additionalInformation._event_.origin),
    );

    if (!extensionName) {
        throw new Error(`Could not find a extension with the given event origin "${additionalInformation._event_.origin}"`);
    }

    const extension = Shopware.Store.get('extensions').extensionsState?.[extensionName];
    if (!extension) {
        throw new Error(
            `Could not find an extension with the given name "${extensionName}" in the extension store (Shopware.Store.get('extensions').extensionsState)`,
        );
    }

    if (extension.integrationId) {
        return Shopware.Service('repositoryFactory').create(entityName, '', {
            'sw-app-integration-id': extension.integrationId,
        });
    }

    return Shopware.Service('repositoryFactory').create(entityName);
}

function rejectRepositoryCreation<EntityName extends keyof EntitySchema.EntityKeys>(entityName: EntityName): unknown {
    return Promise.reject(`Could not create repository for entity "${entityName}"`);
}

/**
 * Merges the context supplied by the extension into the admin API context.
 * The `cacheKey` is removed, because the repository would otherwise treat the merged context as cache options
 * and run the request with the global admin context instead.
 * @param context
 */
function createContext(context: object | undefined): ContextState['api'] {
    const mergedContext: ContextState['api'] & { cacheKey?: unknown } = { ...Shopware.Context.api, ...context };
    delete mergedContext.cacheKey;

    return mergedContext;
}

/**
 * This method mutates the result object and removes the filter properties
 * @param result
 * @param customContext
 */
/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access */
function filterContext(result: any, customContext: any) {
    if (result === null || result === 'undefined') {
        return;
    }

    if (typeof result === 'object') {
        for (const key in result) {
            if (key === 'context') {
                // delete everything inside context except properties of customContext, never expose the auth token
                for (const contextKey in result[key]) {
                    if (contextKey === 'authToken' || !customContext || !customContext[contextKey]) {
                        delete result[key][contextKey];
                    }
                }
            } else {
                filterContext(result[key], customContext);
            }
        }
    }
}

/**
 * @private
 */
export default function initializeExtensionDataLoader(): void {
    Shopware.ExtensionAPI.handle(
        'repositorySearch',
        async ({ entityName, criteria = new Shopware.Data.Criteria(), context }, additionalInformation) => {
            try {
                const repository = getRepository(entityName, additionalInformation);

                if (!repository) {
                    return rejectRepositoryCreation(entityName) as Promise<EntityCollection<typeof entityName>>;
                }

                const mergedContext = createContext(context);

                try {
                    const result = await repository.search(criteria, mergedContext);
                    filterContext(result, context);
                    return result;
                } catch (e) {
                    return Promise.reject(e);
                }
            } catch (error) {
                return Promise.reject(error);
            }
        },
    );

    Shopware.ExtensionAPI.handle(
        'repositoryGet',
        async ({ entityName, id, criteria = new Shopware.Data.Criteria(), context }, additionalInformation) => {
            const repository = getRepository(entityName, additionalInformation);
            if (!repository) {
                return rejectRepositoryCreation(entityName) as Promise<null>;
            }

            const mergedContext = createContext(context);

            // The context has to be filtered on the resolved entity, not on the pending promise
            const result: unknown = await repository.get(id as EntityKey<typeof entityName>, mergedContext, criteria);
            filterContext(result, context);
            return result;
        },
    );

    Shopware.ExtensionAPI.handle('repositorySave', async ({ entityName, entity, context }, additionalInformation) => {
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName) as Promise<void>;
        }

        const mergedContext = createContext(context);

        // Await the save, but never return its result: the repository resolves with the raw request response,
        // whose config carries the admin auth token. The extension only needs to know the save succeeded.
        await repository.save(entity, mergedContext);
    });

    Shopware.ExtensionAPI.handle(
        'repositoryClone',
        async ({ entityName, behavior, entityId, context }, additionalInformation) => {
            const repository = getRepository(entityName, additionalInformation);
            if (!repository) {
                return rejectRepositoryCreation(entityName);
            }

            const mergedContext = createContext(context);

            const result = await repository.clone(
                entityId as EntityKey<typeof entityName>,
                behavior as $TSDangerUnknownObject,
                mergedContext,
            );
            filterContext(result, context);
            return result;
        },
    );

    Shopware.ExtensionAPI.handle('repositoryHasChanges', ({ entityName, entity }, additionalInformation) => {
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName) as Promise<boolean>;
        }

        return repository.hasChanges(entity);
    });

    Shopware.ExtensionAPI.handle('repositorySaveAll', async ({ entityName, entities, context }, additionalInformation) => {
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName) as Promise<void>;
        }

        const mergedContext = createContext(context);

        // Await the save, but never return its result: the repository resolves with the raw request response,
        // whose config carries the admin auth token. The extension only needs to know the save succeeded.
        await repository.saveAll(entities as EntityCollection<typeof entityName>, mergedContext);
    });

    Shopware.ExtensionAPI.handle('repositoryDelete', async ({ entityName, entityId, context }, additionalInformation) => {
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName) as Promise<void>;
        }

        const mergedContext = createContext(context);

        // Await the delete, but never return its result: the repository resolves with the raw request response,
        // whose config carries the admin auth token. The extension only needs to know the delete succeeded.
        await repository.delete(entityId as EntityKey<typeof entityName>, mergedContext);
    });

    Shopware.ExtensionAPI.handle('repositoryCreate', ({ entityName, entityId, context }, additionalInformation) => {
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName) as Promise<Entity<typeof entityName>>;
        }

        const mergedContext = createContext(context);

        const result = repository.create(mergedContext, entityId as EntityKey<typeof entityName>) as Entity<
            keyof EntitySchema.Entities
        >;
        filterContext(result, context);
        return result;
    });
}
