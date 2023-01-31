/**
 * @package admin
 */

const RepositoryFactory = Shopware.Classes._private.RepositoryFactory;
const { EntityHydrator, ChangesetGenerator, EntityFactory } = Shopware.Data;
const ErrorResolverError = Shopware.Data.ErrorResolver;

const customEntityTypes = [{
    name: 'custom_entity_detail',
    icon: 'regular-image-text',
    // ToDo NEXT-22655 - Re-implement, when custom_entity_list page is available
    // }, {
    //     name: 'custom_entity_list',
    //     icon: 'regular-list',
}];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeRepositoryFactory(container) {
    const httpClient = container.httpClient;
    const factoryContainer = this.getContainer('factory');
    const serviceContainer = this.getContainer('service');

    return httpClient.get('_info/entity-schema.json', {
        headers: {
            Authorization: `Bearer ${serviceContainer.loginService.getToken()}`,
        },
    }).then(({ data }) => {
        const entityDefinitionFactory = factoryContainer.entityDefinition;
        const customEntityDefinitionService = serviceContainer.customEntityDefinitionService;
        const cmsPageTypeService = serviceContainer.cmsPageTypeService;
        let hasCmsAwareDefinitions = false;

        Object.entries(data).forEach(([key, value]) => {
            entityDefinitionFactory.add(key, value);

            if (key.startsWith('custom_entity_') || key.startsWith('ce_')) {
                customEntityDefinitionService.addDefinition(value);
                hasCmsAwareDefinitions = hasCmsAwareDefinitions || !!value?.flags?.['cms-aware'];
            }
        });

        if (hasCmsAwareDefinitions) {
            customEntityTypes.forEach((customEntityType) => {
                cmsPageTypeService.register(customEntityType);
            });
        }

        const hydrator = new EntityHydrator();
        const changesetGenerator = new ChangesetGenerator();
        const entityFactory = new EntityFactory();
        const errorResolver = new ErrorResolverError();

        this.addServiceProvider('repositoryFactory', () => {
            return new RepositoryFactory(
                hydrator,
                changesetGenerator,
                entityFactory,
                httpClient,
                errorResolver,
            );
        });
        this.addServiceProvider('entityHydrator', () => {
            return hydrator;
        });
        this.addServiceProvider('entityFactory', () => {
            return entityFactory;
        });
    });
}
