const { Criteria } = Shopware.Data;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createMediaDefaultFolderService() {
    const repository = Shopware.Service('repositoryFactory').create('media_default_folder');

    return {
        getDefaultFolderId: (entityName) => {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', entityName));

            return repository
                .search(criteria, {
                    cacheKey: [
                        'media-default-folder',
                        entityName,
                    ],
                })
                .then((data) => {
                    return data.first().folder.id;
                })
                .catch(() => {
                    return null;
                });
        },
    };
}
