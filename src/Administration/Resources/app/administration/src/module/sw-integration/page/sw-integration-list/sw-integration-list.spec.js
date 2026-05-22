/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-integration/page/sw-integration-list';

const appIntegration = {
    id: 'app-integration-id',
    label: 'MyApp',
    app: { id: 'app-id', active: true },
    aclRoles: [],
    mcpAllowlist: null,
};

async function createWrapper(privileges = [], integrations = null) {
    const defaultIntegrations = integrations ?? [{ id: '44de136acf314e7184401d36406c1e90' }];

    const wrapper = mount(await wrapTestComponent('sw-integration-list', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return Promise.resolve({
                                id: '44de136acf314e7184401d36406c1e90',
                            });
                        },

                        search: () => {
                            return Promise.resolve(defaultIntegrations);
                        },

                        save: () => {
                            return Promise.resolve();
                        },

                        delete: () => {
                            return Promise.resolve();
                        },
                    }),
                },

                integrationService: {
                    generateKey: () => {
                        return Promise.resolve({
                            accessKey: 'SWIANMDUSUR1Q2X0VURGAVDAQG',
                            secretAccessKey: 'YzFnaFprUjdaZUI4WkJsSmVOcHNOTnI5bUNqc2o4YUx0WmFIb3Y',
                        });
                    },
                    saveMcpAllowlist: () => {
                        return Promise.resolve();
                    },
                },

                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },

                feature: {
                    isActive: (flag) => flag === 'MCP_SERVER',
                },
            },

            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-back"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="language-switch"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="side-content"></slot>
                            <slot name="content"></slot>
                            <slot name="sidebar"></slot>
                            <slot></slot>
                        </div>
                        `,
                },
                'sw-card-view': {
                    template: `
                        <div class="sw-card-view">
                            <slot></slot>
                        </div>
                        `,
                },
                'mt-card': {
                    template: `
                        <div class="mt-card">
                            <slot></slot>
                        </div>
                        `,
                },
                'sw-language-switch': true,
                'sw-search-bar': true,
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-field-error': true,
                'sw-field-copyable': true,

                'sw-entity-multi-select': true,
                'sw-entity-listing': {
                    props: [
                        'items',
                        'dataSource',
                        'detailRoute',
                    ],
                    template: `
                        <div>
                            <template v-for="item in (dataSource || items)" :key="item.id">
                                <slot name="actions" v-bind="{ item }">
                                </slot>
                                <slot name="action-modals" v-bind="{ item }">
                                </slot>
                            </template>
                        </div>
                    `,
                },
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),

                'sw-label': true,
                'router-link': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'solid-content',
                        },
                    },
                },
            },
        },
    });

    await flushPromises();
    return wrapper;
}

describe('module/sw-integration/page/sw-integration-list', () => {
    it('should not be able to create / edit without permissions', async () => {
        const wrapper = await createWrapper();

        const createButton = wrapper.find('.sw-integration-list__add-integration-action');
        expect(createButton.attributes().disabled).toBeDefined();

        const editMenuItem = wrapper.find('.sw_integration_list__edit-action');
        expect(editMenuItem.classes()).toContain('is--disabled');

        const deleteMenuItem = wrapper.find('.sw_integration_list__delete-action');
        expect(deleteMenuItem.classes()).toContain('is--disabled');
    });

    it('should be able to create a integration', async () => {
        const wrapper = await createWrapper([
            'integration.creator',
            'integration.editor',
        ]);

        const createButton = wrapper.find('.sw-integration-list__add-integration-action');
        expect(createButton.attributes().disabled).toBeUndefined();
        await createButton.trigger('click');
        await flushPromises();

        const modal = wrapper.find('.sw-modal.sw-integration-list__detail');
        expect(modal.exists()).toBeTruthy();

        const labelField = wrapper.find('#sw-field--currentIntegration-label');
        await labelField.setValue('Test');

        const accessKeyField = wrapper.find('#sw-field--currentIntegration-accessKey');
        expect(accessKeyField.element.value).toBe('SWIANMDUSUR1Q2X0VURGAVDAQG');

        const secretKeyField = wrapper.find('#sw-field--currentIntegration-secretAccessKey');
        expect(secretKeyField.element.value).toBe('YzFnaFprUjdaZUI4WkJsSmVOcHNOTnI5bUNqc2o4YUx0WmFIb3Y');

        const saveButton = wrapper.find('.sw-integration-detail-modal__save-action');
        expect(saveButton.attributes().disabled).toBeUndefined();
        await saveButton.trigger('click');
        await flushPromises();

        const modalAfterSave = wrapper.find('.sw-modal.sw-integration-list__detail');
        expect(modalAfterSave.exists()).toBeFalsy();
    });

    it('should be able to edit a integration', async () => {
        const wrapper = await createWrapper([
            'integration.editor',
        ]);

        const editMenuItem = wrapper.find('.sw_integration_list__edit-action');
        await editMenuItem.trigger('click');
        await flushPromises();

        const modal = wrapper.find('.sw-modal.sw-integration-list__detail');
        expect(modal.exists()).toBeTruthy();

        const labelField = wrapper.find('#sw-field--currentIntegration-label');
        await labelField.setValue('Test2');

        const accessKeyField = wrapper.find('#sw-field--currentIntegration-accessKey');
        expect(accessKeyField.exists()).toBeTruthy();

        // secret field should be hidden on edit
        const secretKeyField = wrapper.find('#sw-field--currentIntegration-secretAccessKey');
        expect(secretKeyField.exists()).toBeFalsy();

        const saveButton = wrapper.find('.sw-integration-detail-modal__save-action');
        expect(saveButton.attributes().disabled).toBeUndefined();
        await saveButton.trigger('click');
        await flushPromises();

        const modalAfterSave = wrapper.find('.sw-modal.sw-integration-list__detail');
        expect(modalAfterSave.exists()).toBeFalsy();
    });

    it('should be able to delete a integration', async () => {
        const wrapper = await createWrapper([
            'integration.deleter',
        ]);

        const deleteMenuItem = wrapper.find('.sw_integration_list__delete-action');
        await deleteMenuItem.trigger('click');
        await flushPromises();

        const deleteModal = wrapper.find('.sw-modal');
        expect(deleteModal.exists()).toBeTruthy();

        const deleteButton = wrapper.findByText('button', 'sw-integration.detail.buttonDelete');
        expect(deleteButton.text()).toBe('sw-integration.detail.buttonDelete');
        await deleteButton.trigger('click');
        await flushPromises();

        const modalAfterDelete = wrapper.find('.sw-modal');
        expect(modalAfterDelete.exists()).toBeFalsy();
    });

    it('should not be able add an integration with admin-role as a non-admin', async () => {
        const wrapper = await createWrapper([
            'integration.viewer',
            'integration.editor',
            'integration.deleter',
        ]);

        const editMenuItem = wrapper.find('.sw_integration_list__edit-action');
        await editMenuItem.trigger('click');
        await flushPromises();

        const adminRoleSwitch = wrapper.findComponent('.sw-settings-user-detail__grid-is-admin');
        expect(adminRoleSwitch.props().disabled).toBe(true);
    });

    it('should disable edit and delete for app integrations', async () => {
        const wrapper = await createWrapper(
            [
                'integration.editor',
                'integration.deleter',
            ],
            [appIntegration],
        );

        const editMenuItem = wrapper.find('.sw_integration_list__edit-action');
        expect(editMenuItem.classes()).toContain('is--disabled');

        const deleteMenuItem = wrapper.find('.sw_integration_list__delete-action');
        expect(deleteMenuItem.classes()).toContain('is--disabled');
    });

    it('should allow editing MCP tools for app integrations', async () => {
        const wrapper = await createWrapper(['integration_mcp.editor'], [appIntegration]);

        const mcpMenuItem = wrapper.find('.sw_integration_list__edit-mcp-action');
        expect(mcpMenuItem.classes()).not.toContain('is--disabled');
    });

    it('should not disable edit and delete for manual integrations', async () => {
        const wrapper = await createWrapper([
            'integration.editor',
            'integration.deleter',
        ]);

        const editMenuItem = wrapper.find('.sw_integration_list__edit-action');
        expect(editMenuItem.classes()).not.toContain('is--disabled');

        const deleteMenuItem = wrapper.find('.sw_integration_list__delete-action');
        expect(deleteMenuItem.classes()).not.toContain('is--disabled');
    });

    it('should call integrationService.saveMcpAllowlist on save', async () => {
        const integration = { ...appIntegration, app: { id: 'app-id', active: true } };
        const saveMock = jest.fn().mockResolvedValue();
        const wrapper = await createWrapper(['integration_mcp.editor'], [integration]);
        wrapper.vm.$.appContext.provides.integrationService.saveMcpAllowlist = saveMock;

        wrapper.vm.mcpIntegration = integration;
        wrapper.vm.pendingMcpAllowlist = ['shopware-entity-read'];

        await wrapper.vm.onSaveMcpAllowlist();
        await flushPromises();

        expect(saveMock).toHaveBeenCalledWith(integration.id, ['shopware-entity-read']);
    });

    it('should gate Edit MCP Tools on integration_mcp.editor not integration.editor', async () => {
        const wrapper = await createWrapper(['integration.editor'], [appIntegration]);

        const mcpMenuItem = wrapper.find('.sw_integration_list__edit-mcp-action');
        expect(mcpMenuItem.classes()).toContain('is--disabled');
    });

    it('should enable Edit MCP Tools with integration_mcp.editor', async () => {
        const wrapper = await createWrapper(['integration_mcp.editor'], [appIntegration]);

        const mcpMenuItem = wrapper.find('.sw_integration_list__edit-mcp-action');
        expect(mcpMenuItem.classes()).not.toContain('is--disabled');
    });

    it('should have integration criteria with filters', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.integrationCriteria;

        expect(criteria.filters).toStrictEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    field: 'deletedAt',
                    type: 'equals',
                    value: null,
                }),
                expect.objectContaining({
                    type: 'multi',
                    operator: 'OR',
                    queries: expect.arrayContaining([
                        expect.objectContaining({ field: 'app.id', type: 'equals', value: null }),
                        expect.objectContaining({ field: 'app.active', type: 'equals', value: true }),
                    ]),
                }),
            ]),
        );
    });
});
