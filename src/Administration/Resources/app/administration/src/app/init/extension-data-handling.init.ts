/**
 * @package admin
 */

import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type EntityCollection from '../../core/data/entity-collection.data';
import type Repository from '../../core/data/repository.data';

function getRepository(
    entityName: keyof EntitySchema.Entities,
    additionalInformation: { _event_: MessageEvent<string>},
): Repository<keyof EntitySchema.Entities> | null {
    const extensionName = Object.keys(Shopware.State.get('extensions'))
        .find(key => Shopware.State.get('extensions')[key].baseUrl.startsWith(additionalInformation._event_.origin));

    if (!extensionName) {
        throw new Error(`Could not find a extension with the given event origin "${additionalInformation._event_.origin}"`);
    }

    const extension = Shopware.State.get('extensions')?.[extensionName];
    if (!extension) {
        // eslint-disable-next-line max-len
        throw new Error(`Could not find an extension with the given name "${extensionName}" in the extension store (Shopware.State.get('extensions'))`);
    }

    if (extension.integrationId) {
        return Shopware.Service('repositoryFactory')
            .create(entityName, '', { 'sw-app-integration-id': extension.integrationId });
    }

    return Shopware.Service('repositoryFactory').create(entityName);
}

function rejectRepositoryCreation(entityName: string): unknown {
    // eslint-disable-next-line prefer-promise-reject-errors
    return Promise.reject(`Could not create repository for entity "${entityName}"`);
}

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default function initializeExtensionDataLoader(): void {
    Shopware.ExtensionAPI.handle('repositorySearch', async (
        {
            entityName,
            criteria = new Shopware.Data.Criteria(),
            context,
        },
        additionalInformation,
    ) => {
        try {
            const repository = getRepository(entityName as keyof EntitySchema.Entities, additionalInformation);

            if (!repository) {
                return rejectRepositoryCreation(
                    entityName as keyof EntitySchema.Entities,
                ) as Promise<EntityCollection<keyof EntitySchema.Entities>>;
            }

            const mergedContext = { ...Shopware.Context.api, ...context };

            try {
                return await repository.search(criteria, mergedContext);
            } catch (e) {
                return Promise.reject(e);
            }
        } catch (error) {
            return Promise.reject(error);
        }
    });

    Shopware.ExtensionAPI.handle('repositoryGet', (
        {
            entityName,
            id,
            criteria = new Shopware.Data.Criteria(),
            context,
        },
        additionalInformation,
    ) => {
        const repository = getRepository(entityName as keyof EntitySchema.Entities, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName as keyof EntitySchema.Entities) as Promise<null>;
        }

        const mergedContext = { ...Shopware.Context.api, ...context };

        return repository.get(id, mergedContext, criteria);
    });

    Shopware.ExtensionAPI.handle('repositorySave', (
        {
            entityName,
            entity,
            context,
        },
        additionalInformation,
    ) => {
        const repository = getRepository(entityName as keyof EntitySchema.Entities, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName as keyof EntitySchema.Entities) as Promise<void>;
        }

        const mergedContext = { ...Shopware.Context.api, ...context };

        return repository.save(entity as Entity<keyof EntitySchema.Entities>, mergedContext) as Promise<void>;
    });

    Shopware.ExtensionAPI.handle('repositoryClone', (
        {
            entityName,
            behavior,
            entityId,
            context,
        },
        additionalInformation,
    ) => {
        const repository = getRepository(entityName as keyof EntitySchema.Entities, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName as keyof EntitySchema.Entities);
        }

        const mergedContext = { ...Shopware.Context.api, ...context };

        return repository.clone(entityId, mergedContext, behavior as $TSDangerUnknownObject);
    });

    Shopware.ExtensionAPI.handle('repositoryHasChanges', (
        {
            entityName,
            entity,
        },
        additionalInformation,
    ) => {
        const repository = getRepository(entityName as keyof EntitySchema.Entities, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName as keyof EntitySchema.Entities) as Promise<boolean>;
        }

        return repository.hasChanges(entity as Entity<keyof EntitySchema.Entities>);
    });

    Shopware.ExtensionAPI.handle('repositorySaveAll', (
        {
            entityName,
            entities,
            context,
        },
        additionalInformation,
    ) => {
        const repository = getRepository(entityName as keyof EntitySchema.Entities, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName as keyof EntitySchema.Entities)as Promise<void>;
        }

        const mergedContext = { ...Shopware.Context.api, ...context };

        return repository.saveAll(entities as EntityCollection<keyof EntitySchema.Entities>, mergedContext) as Promise<void>;
    });

    Shopware.ExtensionAPI.handle('repositoryDelete', (
        {
            entityName,
            entityId,
            context,
        },
        additionalInformation,
    ) => {
        const repository = getRepository(entityName as keyof EntitySchema.Entities, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName as keyof EntitySchema.Entities)as Promise<void>;
        }

        const mergedContext = { ...Shopware.Context.api, ...context };

        return repository.delete(entityId, mergedContext) as unknown as Promise<void>;
    });

    Shopware.ExtensionAPI.handle('repositoryCreate', (
        {
            entityName,
            entityId,
            context,
        },
        additionalInformation,
    ) => {
        const repository = getRepository(entityName as keyof EntitySchema.Entities, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(
                entityName as keyof EntitySchema.Entities,
            ) as Promise<Entity<keyof EntitySchema.Entities>>;
        }

        const mergedContext = { ...Shopware.Context.api, ...context };

        return repository.create(mergedContext, entityId);
    });
}
