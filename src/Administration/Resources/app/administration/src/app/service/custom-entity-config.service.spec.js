import CustomEntityDefinitionService from 'src/app/service/custom-entity-definition.service';

const bareDefinitionName = 'custom_entity_bare';
const customEntityDefinitionBare = {
    entity: bareDefinitionName,
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

    customEntityDefinitionService.addDefinition(customEntityDefinitionBare);
    customEntityDefinitionService.addDefinition(customEntityDefinitionWithAdminUi);

    return customEntityDefinitionService;
}

describe('src/app/service/custom-entity-definition.service', () => {
    it('should get a definition by name', () => {
        const service = createCustomEntityDefinitionService();

        expect(service.getDefinitionByName(bareDefinitionName)).toStrictEqual(customEntityDefinitionBare);
    });

    it('should get all definitions', () => {
        const service = createCustomEntityDefinitionService();

        expect(service.getAllDefinitions()).toStrictEqual([customEntityDefinitionBare, customEntityDefinitionWithAdminUi]);
    });

    it('should check wether an entity with a admin-ui definition exists', () => {
        const service = createCustomEntityDefinitionService();

        expect(service.hasDefinitionWithAdminUi(bareDefinitionName)).toBe(false);
        expect(service.hasDefinitionWithAdminUi(withAdminUiName)).toBe(true);
    });

    it('should produce menu entry definitions for each entity with admin-ui', () => {
        const service = createCustomEntityDefinitionService();

        expect(service.getMenuEntries()).toStrictEqual([{
            icon: undefined,
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
