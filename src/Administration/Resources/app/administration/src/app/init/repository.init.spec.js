import initializeRepositoryFactory from 'src/app/init/repository.init';

const coreEntityName = 'product';
const coreEntityConfig = {
    entity: coreEntityName,
};

const bareConfigName = 'custom_entity_bare';
const customEntityDefinitionBare = {
    entity: bareConfigName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {}
};

const withAdminUiName = 'custom_entity_with_admin_ui';
const customEntityDefinitionWithAdminUi = {
    entity: withAdminUiName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {
        'cms-aware': {},
        'admin-ui': {}
    }
};

const shortHandWithAdminUiName = 'ce_with_admin_ui';
const shortHandCustomEntityDefinitionWithAdminUi = {
    entity: shortHandWithAdminUiName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {
        'cms-aware': {},
        'admin-ui': {}
    }
};

const container = {
    httpClient: {
        get() {
            return Promise.resolve({
                data: {
                    [bareConfigName]: customEntityDefinitionBare,
                    [coreEntityName]: coreEntityConfig,
                    [withAdminUiName]: customEntityDefinitionWithAdminUi,
                    [shortHandWithAdminUiName]: shortHandCustomEntityDefinitionWithAdminUi,
                }
            });
        }
    }
};

const factory = {
    entityDefinition: {
        add() {}
    },
};


const customEntityDefinitionStore = [];

const service = {
    customEntityDefinitionService: {
        addDefinition(config) {
            customEntityDefinitionStore.push(config);
        }
    },
    loginService: {
        getToken: () => ''
    }
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
    addServiceProvider() {}
};

describe('init/repository', () => {
    it('should register custom entities to the customEntityDefinitionService', async () => {
        await initializeRepositoryFactory.apply(thisMock, [container]);

        expect(customEntityDefinitionStore).toStrictEqual(
            [customEntityDefinitionBare, customEntityDefinitionWithAdminUi, shortHandCustomEntityDefinitionWithAdminUi]
        );
    });
});
