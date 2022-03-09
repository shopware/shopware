import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';

export default function initializeExtensionDataLoader(): void {
    Shopware.ExtensionAPI.handle('repositorySearch', (
        {
            entityName,
            criteria = new Shopware.Data.Criteria(),
            context,
        },
    ) => {
        const repository = Shopware.Service('repositoryFactory').create(entityName);
        const mergedContext = { ...Shopware.Context.api, ...context };

        // TODO NEXT-20525: add header parameter for app privileges context
        return repository.search(criteria, mergedContext);
    });

    Shopware.ExtensionAPI.handle('repositoryGet', (
        {
            entityName,
            id,
            criteria = new Shopware.Data.Criteria(),
            context,
        },
    ) => {
        const repository = Shopware.Service('repositoryFactory').create(entityName);
        const mergedContext = { ...Shopware.Context.api, ...context };

        // TODO NEXT-20525: add header parameter for app privileges context
        return repository.get(id, mergedContext, criteria);
    });

    Shopware.ExtensionAPI.handle('repositorySave', (
        {
            entityName,
            entity,
            context,
        },
    ) => {
        const repository = Shopware.Service('repositoryFactory').create(entityName);
        const mergedContext = { ...Shopware.Context.api, ...context };

        // TODO NEXT-20525: add header parameter for app privileges context
        return repository.save(entity, mergedContext) as Promise<void>;
    });

    Shopware.ExtensionAPI.handle('repositoryClone', (
        {
            entityName,
            behavior,
            entityId,
            context,
        },
    ) => {
        const repository = Shopware.Service('repositoryFactory').create(entityName);
        const mergedContext = { ...Shopware.Context.api, ...context };

        // TODO NEXT-20525: add header parameter for app privileges context
        return repository.clone(entityId, mergedContext, behavior as $TSDangerUnknownObject);
    });

    Shopware.ExtensionAPI.handle('repositoryHasChanges', (
        {
            entityName,
            entity,
        },
    ) => {
        const repository = Shopware.Service('repositoryFactory').create(entityName);

        // TODO NEXT-20525: add header parameter for app privileges context
        return repository.hasChanges(entity);
    });

    Shopware.ExtensionAPI.handle('repositorySaveAll', (
        {
            entityName,
            entities,
            context,
        },
    ) => {
        const repository = Shopware.Service('repositoryFactory').create(entityName);
        const mergedContext = { ...Shopware.Context.api, ...context };

        // TODO NEXT-20525: add header parameter for app privileges context
        return repository.saveAll(entities, mergedContext) as Promise<void>;
    });

    Shopware.ExtensionAPI.handle('repositoryDelete', (
        {
            entityName,
            entityId,
            context,
        },
    ) => {
        const repository = Shopware.Service('repositoryFactory').create(entityName);
        const mergedContext = { ...Shopware.Context.api, ...context };

        // TODO NEXT-20525: add header parameter for app privileges context
        return repository.delete(entityId, mergedContext) as unknown as Promise<void>;
    });

    Shopware.ExtensionAPI.handle('repositoryCreate', (
        {
            entityName,
            entityId,
            context,
        },
    ) => {
        const repository = Shopware.Service('repositoryFactory').create(entityName);
        const mergedContext = { ...Shopware.Context.api, ...context };

        // TODO NEXT-20525: add header parameter for app privileges context
        return repository.create(mergedContext, entityId) as Entity;
    });
}
