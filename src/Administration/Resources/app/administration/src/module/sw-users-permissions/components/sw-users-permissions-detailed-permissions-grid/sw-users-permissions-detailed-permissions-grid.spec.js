/**
 * @package services-settings
 */
// eslint-disable-next-line
import fs from 'fs';
// eslint-disable-next-line
import path from 'path';
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import PrivilegesService from 'src/app/service/privileges.service';

async function createWrapper({ privilegesMappings = [], rolePrivileges = [], detailedPrivileges = [] } = {}) {
    const privilegesService = new PrivilegesService();
    privilegesMappings.forEach((mapping) => {
        privilegesService.addPrivilegeMappingEntry(mapping);
    });

    return mount(
        await wrapTestComponent('sw-users-permissions-detailed-permissions-grid', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-card': true,
                },
                provide: {
                    privileges: privilegesService,
                },
            },
            props: reactive({
                role: { privileges: rolePrivileges },
                detailedPrivileges: detailedPrivileges,
            }),
        },
    );
}

let entitySchema;

describe('src/module/sw-users-permissions/components/sw-users-permissions-detailed-permissions-grid', () => {
    beforeAll(async () => {
        const entityDefinitionFactory = Shopware.Application.getContainer('factory').entityDefinition;
        const entitySchemaMockPath = path.join(__dirname, './_mock/entity-schema.json');

        entitySchema = JSON.parse(fs.readFileSync(entitySchemaMockPath, 'utf8'));

        Object.entries(entitySchema).forEach(
            ([
                name,
                value,
            ]) => {
                entityDefinitionFactory.add(name, value);
            },
        );
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the header titles', async () => {
        const wrapper = await createWrapper();

        // eslint-disable-next-line max-len
        const headerEntries = wrapper.findAll(
            '.sw-users-permissions-detailed-permissions-grid__entry-header .sw-users-permissions-detailed-permissions-grid__checkbox-wrapper',
        );

        expect(headerEntries.at(0).text()).toBe('sw-privileges.permissionType.read');
        expect(headerEntries.at(1).text()).toBe('sw-privileges.permissionType.update');
        expect(headerEntries.at(2).text()).toBe('sw-privileges.permissionType.create');
        expect(headerEntries.at(3).text()).toBe('sw-privileges.permissionType.delete');
    });

    it('should render a row for each entity with all checkboxes enabled', async () => {
        const wrapper = await createWrapper();

        Object.keys(entitySchema).forEach((entityName) => {
            const entityRow = wrapper.find(`.sw-users-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityTitle = entityRow.find('.sw-users-permissions-detailed-permissions-grid__title');
            expect(entityTitle.text()).toBe(entityName);

            // skip default values
            if (
                [
                    'language',
                    'locale',
                    'message_queue_stats',
                ].includes(entityName)
            ) {
                return;
            }

            const entityReadInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_delete input');

            // should exist
            expect(entityReadInput.exists()).toBeTruthy();
            expect(entityUpdateInput.exists()).toBeTruthy();
            expect(entityDeleteInput.exists()).toBeTruthy();
            expect(entityCreateInput.exists()).toBeTruthy();

            // not disabled
            expect(entityReadInput.attributes().disabled).toBeUndefined();
            expect(entityUpdateInput.attributes().disabled).toBeUndefined();
            expect(entityDeleteInput.attributes().disabled).toBeUndefined();
            expect(entityCreateInput.attributes().disabled).toBeUndefined();

            // not checked
            expect(entityReadInput.element.checked).toBeFalsy();
            expect(entityUpdateInput.element.checked).toBeFalsy();
            expect(entityDeleteInput.element.checked).toBeFalsy();
            expect(entityCreateInput.element.checked).toBeFalsy();
        });
    });

    it('should render a row for each entity with all checkboxes disabled when prop disabled is true', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        Object.keys(entitySchema).forEach((entityName) => {
            const entityRow = wrapper.find(`.sw-users-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityReadInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_delete input');

            // to be disabled
            expect(entityReadInput.attributes().disabled).toBeDefined();
            expect(entityUpdateInput.attributes().disabled).toBeDefined();
            expect(entityDeleteInput.attributes().disabled).toBeDefined();
            expect(entityCreateInput.attributes().disabled).toBeDefined();
        });
    });

    it('should render a row for each entity with all checkboxes enabled and product and category read checked', async () => {
        const wrapper = await createWrapper({
            rolePrivileges: ['product.viewer'],
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            privileges: [
                                'product:read',
                                'document:read',
                            ],
                            dependencies: [],
                        },
                        editor: {
                            privileges: [
                                'product:update',
                                'document:update',
                            ],
                            dependencies: ['product.viewer'],
                        },
                    },
                },
            ],
        });

        [
            'product',
            'document',
        ].forEach((entityName) => {
            const entityRow = wrapper.find(`.sw-users-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityReadInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_delete input');

            // should exist
            expect(entityReadInput.exists()).toBeTruthy();
            expect(entityUpdateInput.exists()).toBeTruthy();
            expect(entityDeleteInput.exists()).toBeTruthy();
            expect(entityCreateInput.exists()).toBeTruthy();

            // read should be disabled
            expect(entityReadInput.attributes().disabled).toBeDefined();
            expect(entityUpdateInput.attributes().disabled).toBeUndefined();
            expect(entityDeleteInput.attributes().disabled).toBeUndefined();
            expect(entityCreateInput.attributes().disabled).toBeUndefined();

            // not checked
            expect(entityReadInput.element.checked).toBe(true);
            expect(entityUpdateInput.element.checked).toBeFalsy();
            expect(entityDeleteInput.element.checked).toBeFalsy();
            expect(entityCreateInput.element.checked).toBeFalsy();
        });

        ['order'].forEach((entityName) => {
            const entityRow = wrapper.find(`.sw-users-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityReadInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_delete input');

            // should exist
            expect(entityReadInput.exists()).toBeTruthy();
            expect(entityUpdateInput.exists()).toBeTruthy();
            expect(entityDeleteInput.exists()).toBeTruthy();
            expect(entityCreateInput.exists()).toBeTruthy();

            // not disabled
            expect(entityReadInput.attributes().disabled).toBeUndefined();
            expect(entityUpdateInput.attributes().disabled).toBeUndefined();
            expect(entityDeleteInput.attributes().disabled).toBeUndefined();
            expect(entityCreateInput.attributes().disabled).toBeUndefined();

            // not checked
            expect(entityReadInput.element.checked).toBeFalsy();
            expect(entityUpdateInput.element.checked).toBeFalsy();
            expect(entityDeleteInput.element.checked).toBeFalsy();
            expect(entityCreateInput.element.checked).toBeFalsy();
        });
    });

    it('should render a row for each entity with all checkboxes enabled and product and category read and update checked', async () => {
        const wrapper = await createWrapper({
            rolePrivileges: [
                'product.viewer',
                'product.editor',
            ],
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            privileges: [
                                'product:read',
                                'document:read',
                            ],
                            dependencies: [],
                        },
                        editor: {
                            privileges: [
                                'product:update',
                                'document:update',
                            ],
                            dependencies: ['product.viewer'],
                        },
                    },
                },
            ],
        });

        [
            'product',
            'document',
        ].forEach((entityName) => {
            const entityRow = wrapper.find(`.sw-users-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityReadInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_delete input');

            // should exist
            expect(entityReadInput.exists()).toBeTruthy();
            expect(entityUpdateInput.exists()).toBeTruthy();
            expect(entityDeleteInput.exists()).toBeTruthy();
            expect(entityCreateInput.exists()).toBeTruthy();

            // read and update should be disabled
            expect(entityReadInput.attributes().disabled).toBeDefined();
            expect(entityUpdateInput.attributes().disabled).toBeDefined();
            expect(entityDeleteInput.attributes().disabled).toBeUndefined();
            expect(entityCreateInput.attributes().disabled).toBeUndefined();

            // not checked
            expect(entityReadInput.element.checked).toBe(true);
            expect(entityUpdateInput.element.checked).toBe(true);
            expect(entityDeleteInput.element.checked).toBeFalsy();
            expect(entityCreateInput.element.checked).toBeFalsy();
        });

        ['order'].forEach((entityName) => {
            const entityRow = wrapper.find(`.sw-users-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityReadInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.sw-users-permissions-detailed-permissions-grid__role_delete input');

            // should exist
            expect(entityReadInput.exists()).toBeTruthy();
            expect(entityUpdateInput.exists()).toBeTruthy();
            expect(entityDeleteInput.exists()).toBeTruthy();
            expect(entityCreateInput.exists()).toBeTruthy();

            // not disabled
            expect(entityReadInput.attributes().disabled).toBeUndefined();
            expect(entityUpdateInput.attributes().disabled).toBeUndefined();
            expect(entityDeleteInput.attributes().disabled).toBeUndefined();
            expect(entityCreateInput.attributes().disabled).toBeUndefined();

            // not checked
            expect(entityReadInput.element.checked).toBeFalsy();
            expect(entityUpdateInput.element.checked).toBeFalsy();
            expect(entityDeleteInput.element.checked).toBeFalsy();
            expect(entityCreateInput.element.checked).toBeFalsy();
        });
    });

    it('should be able to check the checkboxes', async () => {
        const wrapper = await createWrapper({
            rolePrivileges: [
                'product.viewer',
                'product.editor',
            ],
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            privileges: [
                                'product:read',
                                'document:read',
                            ],
                            dependencies: [],
                        },
                        editor: {
                            privileges: [
                                'product:update',
                                'document:update',
                            ],
                            dependencies: ['product.viewer'],
                        },
                    },
                },
            ],
        });

        const privileges = wrapper.props().role.privileges;
        const detailedPrivileges = wrapper.props().detailedPrivileges;

        expect(privileges).toEqual([
            'product.viewer',
            'product.editor',
        ]);
        expect(detailedPrivileges).toEqual([]);

        const orderRow = wrapper.find('.sw-users-permissions-detailed-permissions-grid__entry_order');
        const orderUpdateInput = orderRow.find('.sw-users-permissions-detailed-permissions-grid__role_update input');
        const orderCreateInput = orderRow.find('.sw-users-permissions-detailed-permissions-grid__role_create input');

        await orderUpdateInput.setChecked();
        await orderCreateInput.setChecked();

        expect(privileges).toEqual([
            'product.viewer',
            'product.editor',
        ]);
        expect(detailedPrivileges).toEqual([
            'order:update',
            'order:create',
        ]);
    });

    it('should be able to uncheck the checkboxes', async () => {
        const wrapper = await createWrapper({
            rolePrivileges: [
                'product.viewer',
                'product.editor',
            ],
            detailedPrivileges: [
                'order:update',
                'order:create',
            ],
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'product',
                    parent: null,
                    roles: {
                        viewer: {
                            privileges: [
                                'product:read',
                                'document:read',
                            ],
                            dependencies: [],
                        },
                        editor: {
                            privileges: [
                                'product:update',
                                'document:update',
                            ],
                            dependencies: ['product.viewer'],
                        },
                    },
                },
            ],
        });

        const privileges = wrapper.props().role.privileges;
        const detailedPrivileges = wrapper.props().detailedPrivileges;

        expect(privileges).toEqual([
            'product.viewer',
            'product.editor',
        ]);
        expect(detailedPrivileges).toEqual([
            'order:update',
            'order:create',
        ]);

        const orderRow = wrapper.find('.sw-users-permissions-detailed-permissions-grid__entry_order');
        const orderUpdateInput = orderRow.find('.sw-users-permissions-detailed-permissions-grid__role_update input');

        await orderUpdateInput.setChecked(false);

        expect(privileges).toEqual([
            'product.viewer',
            'product.editor',
        ]);
        expect(detailedPrivileges).toEqual(['order:create']);
    });
});
