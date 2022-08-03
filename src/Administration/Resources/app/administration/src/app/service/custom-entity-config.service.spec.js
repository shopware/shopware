import CustomEntityDefinitionService from 'src/app/service/custom-entity-definition.service';

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
        'admin-ui': {
            navigationParent: 'content',
            position: '50',
            listing: {
                columns: [{
                    ref: 'title',
                }, {
                    ref: 'description',
                }, {
                    ref: 'position',
                    hidden: true
                }],
            },
            detail: {
                tabs: [{
                    name: 'main',
                    cards: [{
                        name: 'general',
                        fields: [{
                            ref: 'title'
                        }, {
                            ref: 'description',
                            helpText: true,
                            placeholder: true
                        }, {
                            ref: 'position'
                        }]
                    }]
                }]
            }
        }
    }
};

function createCustomEntityDefinitionService() {
    const customEntityDefinitionService = new CustomEntityDefinitionService();

    customEntityDefinitionService.addConfig(customEntityDefinitionBare);
    customEntityDefinitionService.addConfig(customEntityDefinitionWithAdminUi);

    return customEntityDefinitionService;
}

describe('src/app/service/custom-entity-definition.service', () => {
    it('should get a config by name', () => {
        const service = createCustomEntityDefinitionService();

        expect(service.getConfigByName(bareConfigName)).toStrictEqual(customEntityDefinitionBare);
    });

    it('should get all configs', () => {
        const service = createCustomEntityDefinitionService();

        expect(service.getAllConfigs()).toStrictEqual([customEntityDefinitionBare, customEntityDefinitionWithAdminUi]);
    });

    it('should check wether an entity with a admin-ui config exists', () => {
        const service = createCustomEntityDefinitionService();

        expect(service.hasConfigWithAdminUi(bareConfigName)).toBe(false);
        expect(service.hasConfigWithAdminUi(withAdminUiName)).toBe(true);
    });

    it('should produce menu entry configs for each entity with admin-ui', () => {
        const service = createCustomEntityDefinitionService();

        expect(service.getMenuEntries()).toStrictEqual([{
            id: 'custom-entity/custom_entity_with_admin_ui',
            label: 'custom_entity_with_admin_ui.moduleTitle',
            moduleType: 'plugin',
            parent: 'content',
            params: {
                entityName: 'custom_entity_with_admin_ui',
            },
            path: 'sw.custom.entity.index',
            position: '50'
        }]);
    });
});
