/**
 * @package admin
 */

import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type EntityCollection from '../../core/data/entity-collection.data';
import type Repository from '../../core/data/repository.data';

function getRepository(entityName: string, additionalInformation: { _event_: MessageEvent<string>}): Repository | null {
    const extensionName = Object.keys(Shopware.State.get('extensions'))
        .find(key => Shopware.State.get('extensions')[key].baseUrl.startsWith(additionalInformation._event_.origin));

    if (!extensionName) {
        return null;
    }

    const extension = Shopware.State.get('extensions')?.[extensionName];
    if (!extension) {
        return null;
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

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeExtensionDataLoader(): void {
    Shopware.ExtensionAPI.handle('repositorySearch', (
        {
            entityName,
            criteria = new Shopware.Data.Criteria(),
            context,
        },
        additionalInformation,
    ) => {
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName) as Promise<EntityCollection>;
        }

        const mergedContext = { ...Shopware.Context.api, ...context };

        return repository.search(criteria, mergedContext);
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
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName) as Promise<null>;
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
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName) as Promise<void>;
        }

        const mergedContext = { ...Shopware.Context.api, ...context };

        return repository.save(entity, mergedContext) as Promise<void>;
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
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName);
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
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName) as Promise<boolean>;
        }

        return repository.hasChanges(entity);
    });

    Shopware.ExtensionAPI.handle('repositorySaveAll', (
        {
            entityName,
            entities,
            context,
        },
        additionalInformation,
    ) => {
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName)as Promise<void>;
        }

        const mergedContext = { ...Shopware.Context.api, ...context };

        return repository.saveAll(entities, mergedContext) as Promise<void>;
    });

    Shopware.ExtensionAPI.handle('repositoryDelete', (
        {
            entityName,
            entityId,
            context,
        },
        additionalInformation,
    ) => {
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName)as Promise<void>;
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
        const repository = getRepository(entityName, additionalInformation);
        if (!repository) {
            return rejectRepositoryCreation(entityName) as Promise<Entity>;
        }

        const mergedContext = { ...Shopware.Context.api, ...context };

        return repository.create(mergedContext, entityId) as Entity;
    });
}
