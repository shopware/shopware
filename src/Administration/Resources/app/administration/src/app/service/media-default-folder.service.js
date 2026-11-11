const { Criteria } = Shopware.Data;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createMediaDefaultFolderService() {
    const repository = Shopware.Service('repositoryFactory').create('media_folder');

    return {
        getDefaultFolderId: (entityName) => {
            const criteria = new Criteria(1, 1);
            criteria.setTotalCountMode(0);
            criteria.addFilter(Criteria.equals('defaultFolder.entity', entityName));

            return repository
                .searchIds(criteria, {
                    cacheKey: ['media-default-folder', entityName, 'folder-id'],
                })
                .then(({ data }) => {
                    return data[0] ?? null;
                })
                .catch(() => {
                    return null;
                });
        },
    };
}
