import initializeRepositoryFactory from './repository.init';

const coreEntityName = 'product';
const coreEntityConfig = {
    entity: coreEntityName,
};

const bareConfigName = 'whatever_bare';
const bareCustomEntityConfigName = 'custom_entity_bare';
const customEntityDefinitionBare = {
    entity: bareConfigName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {},
};
const customEntityCmsAwareTypes = [{
    name: 'custom_entity_detail',
    icon: 'regular-image-text',
    // ToDo NEXT-22655 - Re-implement, when custom_entity_list page is available
    // }, {
    //     name: 'custom_entity_list',
    //     icon: 'regular-list',
}];

const withAdminUiName = 'custom_entity_with_admin_ui';
const customEntityDefinitionWithAdminUi = {
    entity: withAdminUiName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {
        'cms-aware': {},
        'admin-ui': {},
    },
};

const shortHandWithAdminUiName = 'ce_with_admin_ui';
const shortHandCustomEntityDefinitionWithAdminUi = {
    entity: shortHandWithAdminUiName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {
        'cms-aware': {},
        'admin-ui': {},
    },
};

const containerWithCmsAware = {
    httpClient: {
        get() {
            return Promise.resolve({
                data: {
                    [bareCustomEntityConfigName]: customEntityDefinitionBare,
                    [coreEntityName]: coreEntityConfig,
                    [withAdminUiName]: customEntityDefinitionWithAdminUi,
                    [shortHandWithAdminUiName]: shortHandCustomEntityDefinitionWithAdminUi,
                },
            });
        },
    },
};

const containerWithoutCmsAware = {
    httpClient: {
        get() {
            return Promise.resolve({
                data: {
                    [bareConfigName]: customEntityDefinitionBare,
                    [coreEntityName]: coreEntityConfig,
                },
            });
        },
    },
};

const factory = {
    entityDefinition: {
        add() {},
    },
};

let customEntityDefinitionStore = [];
let cmsPageTypeDefinitionStore = [];

const service = {
    customEntityDefinitionService: {
        addDefinition(config) {
            customEntityDefinitionStore.push(config);
        },
    },
    cmsPageTypeService: {
        register(pageType) {
            cmsPageTypeDefinitionStore.push(pageType);
        },
    },
    loginService: {
        getToken: () => '',
    },
};

const thisMock = {
    getContainer(containerName) {
        switch (containerName) {
            case 'factory':
                return factory;
            case 'service':
                return service;
            default:
                throw new Error(`Container for ${containerName} isn't mocked`);
        }
    },
    addServiceProvider() {},
};

describe('init/repository', () => {
    beforeEach(() => {
        customEntityDefinitionStore = [];
        cmsPageTypeDefinitionStore = [];
    });

    it('should register custom entities to the customEntityDefinitionService', async () => {
        await initializeRepositoryFactory.apply(thisMock, [containerWithCmsAware]);

        expect(customEntityDefinitionStore).toStrictEqual(
            [customEntityDefinitionBare, customEntityDefinitionWithAdminUi, shortHandCustomEntityDefinitionWithAdminUi],
        );
    });

    it('should register page types to the cmsPageTypeService if an entity is cms-aware', async () => {
        await initializeRepositoryFactory.apply(thisMock, [containerWithCmsAware]);

        expect(cmsPageTypeDefinitionStore).toStrictEqual(customEntityCmsAwareTypes);
    });

    it('should register np page types to the cmsPageTypeService if no entities are cms-aware', async () => {
        await initializeRepositoryFactory.apply(thisMock, [containerWithoutCmsAware]);

        expect(cmsPageTypeDefinitionStore).toStrictEqual([]);
    });
});
