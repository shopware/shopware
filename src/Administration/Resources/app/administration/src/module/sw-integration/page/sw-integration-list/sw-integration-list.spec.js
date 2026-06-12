/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-integration/page/sw-integration-list';

const defaultIntegration = {
    id: '44de136acf314e7184401d36406c1e90',
    label: 'Test integration',
    admin: false,
    aclRoles: [],
};

const appIntegration = {
    id: 'app-integration-id',
    label: 'MyApp',
    app: { id: 'app-id', active: true },
    aclRoles: [],
    mcpAllowlist: null,
};

async function createWrapper(privileges = [], integrations = null, options = {}) {
    const defaultIntegrations = integrations ?? [defaultIntegration];
    const saveMock = options.saveMock ?? jest.fn().mockResolvedValue();
    const searchMock = options.searchMock ?? jest.fn().mockResolvedValue(defaultIntegrations);
    const updateAdminMock = options.updateAdminMock ?? jest.fn().mockResolvedValue();

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

                        search: searchMock,

                        save: saveMock,

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
                    updateAdmin: updateAdminMock,
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
                'mt-data-table': {
                    name: 'mt-data-table',
                    props: [
                        'dataSource',
                        'columns',
                        'disableEdit',
                        'disableDelete',
                        'disableSearch',
                        'searchValue',
                        'filters',
                        'appliedFilters',
                        'numberOfResults',
                        'additionalContextButtons',
                    ],
                    emits: [
                        'open-details',
                        'item-delete',
                        'context-select',
                        'search-value-change',
                        'update:applied-filters',
                    ],
                    template: `
                        <div class="mt-data-table">
                            <template
                                v-for="data in dataSource"
                                :key="data.id"
                            >
                                <slot
                                    name="column-label"
                                    v-bind="{ data }"
                                ></slot>
                                <slot
                                    name="column-permissions"
                                    v-bind="{ data }"
                                ></slot>
                            </template>
                        </div>
                    `,
                },
                'mt-badge': {
                    template: '<span class="mt-badge"><slot></slot></span>',
                },
                'mt-empty-state': true,
                'sw-label': true,
                'sw-integration-mcp-allowlist': true,
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

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        expect(dataTable.props('disableEdit')).toBe(true);
        expect(dataTable.props('disableDelete')).toBe(true);
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

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        dataTable.vm.$emit('open-details', wrapper.vm.integrations[0]);
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

    it('should update the admin flag through the integration service', async () => {
        const integration = {
            id: '44de136acf314e7184401d36406c1e90',
            label: 'Test integration',
            admin: true,
            aclRoles: [],
            getOrigin: () => {
                return { admin: false };
            },
        };
        const saveMock = jest.fn().mockResolvedValue();
        const updateAdminMock = jest.fn().mockResolvedValue();
        const searchMock = jest.fn().mockResolvedValue([integration]);

        const wrapper = await createWrapper(
            [
                'admin',
                'integration.editor',
            ],
            [integration],
            {
                saveMock,
                searchMock,
                updateAdminMock,
            },
        );

        await wrapper.vm.updateIntegration(integration);
        await flushPromises();

        expect(saveMock).toHaveBeenCalledWith(integration);
        expect(updateAdminMock).toHaveBeenCalledWith(integration.id, true);
        expect(searchMock).toHaveBeenCalledTimes(2);
    });

    it('should not update the admin flag when it was not changed', async () => {
        const integration = {
            id: '44de136acf314e7184401d36406c1e90',
            label: 'Test integration',
            admin: false,
            aclRoles: [],
            getOrigin: () => {
                return { admin: false };
            },
        };
        const updateAdminMock = jest.fn().mockResolvedValue();

        const wrapper = await createWrapper(
            [
                'admin',
                'integration.editor',
            ],
            [integration],
            {
                updateAdminMock,
            },
        );

        await wrapper.vm.updateIntegration(integration);
        await flushPromises();

        expect(updateAdminMock).not.toHaveBeenCalled();
    });

    it('should be able to delete a integration', async () => {
        const wrapper = await createWrapper([
            'integration.deleter',
        ]);

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        dataTable.vm.$emit('item-delete', wrapper.vm.integrations[0]);
        await flushPromises();

        const deleteModal = wrapper.find('.sw-modal');
        expect(deleteModal.exists()).toBeTruthy();

        const deleteButton = wrapper.findByText('button', 'global.default.delete');
        expect(deleteButton.text()).toBe('global.default.delete');
        expect(deleteButton.classes()).toContain('mt-button--critical');
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

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        dataTable.vm.$emit('open-details', wrapper.vm.integrations[0]);
        await flushPromises();

        const adminRoleSwitch = wrapper.findComponent('.sw-settings-user-detail__grid-is-admin');
        expect(adminRoleSwitch.props().disabled).toBe(true);
    });

    it('should not open the edit or delete modal for app integrations', async () => {
        const wrapper = await createWrapper(
            [
                'integration.editor',
                'integration.deleter',
            ],
            [appIntegration],
        );

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        dataTable.vm.$emit('open-details', wrapper.vm.integrations[0]);
        await flushPromises();

        expect(wrapper.find('.sw-modal.sw-integration-list__detail').exists()).toBeFalsy();

        dataTable.vm.$emit('item-delete', wrapper.vm.integrations[0]);
        await flushPromises();

        expect(wrapper.find('.sw-modal').exists()).toBeFalsy();
    });

    it('should expose MCP context action when permitted', async () => {
        const wrapper = await createWrapper(['integration_mcp.editor'], [appIntegration]);

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        expect(dataTable.props('additionalContextButtons')).toEqual([
            expect.objectContaining({
                key: 'edit-mcp',
            }),
        ]);
    });

    it('should not expose MCP context action without permission', async () => {
        const wrapper = await createWrapper(['integration.editor'], [appIntegration]);

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        expect(dataTable.props('additionalContextButtons')).toStrictEqual([]);
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

    it('should request the integration page size', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.integrationCriteria;

        expect(criteria.page).toBe(1);
        expect(criteria.limit).toBe(25);
    });

    it('should hide integration table pagination when all integrations fit on one page', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.showIntegrationPagination).toBe(false);

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        expect(dataTable.classes()).toContain('sw-integration-list__overview-table--hide-pagination');
    });

    it('should show integration table pagination when more pages are available', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.integrations = Object.assign([...wrapper.vm.integrations], {
            total: wrapper.vm.limit + 1,
        });
        await flushPromises();

        expect(wrapper.vm.showIntegrationPagination).toBe(true);

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        expect(dataTable.classes()).not.toContain('sw-integration-list__overview-table--hide-pagination');
    });

    it('should keep integration table pagination visible after selecting a larger page size', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.limit = 50;
        wrapper.vm.integrations = Object.assign([...wrapper.vm.integrations], {
            total: 30,
        });
        await flushPromises();

        expect(wrapper.vm.showIntegrationPagination).toBe(true);

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        expect(dataTable.classes()).not.toContain('sw-integration-list__overview-table--hide-pagination');
    });

    it('should keep search and filters disabled for small integration lists', async () => {
        const wrapper = await createWrapper();
        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });

        expect(dataTable.props('disableSearch')).toBe(true);
        expect(dataTable.props('filters')).toStrictEqual([]);
    });

    it('should enable search and filters for large integration lists', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.integrations = Object.assign([...wrapper.vm.integrations], {
            total: 30,
        });
        await flushPromises();

        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });
        expect(dataTable.props('disableSearch')).toBe(false);
        expect(dataTable.props('filters')).toStrictEqual([
            expect.objectContaining({
                id: 'permissions',
            }),
        ]);
    });

    it('should search integrations through the repository criteria', async () => {
        const wrapper = await createWrapper();
        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });

        dataTable.vm.$emit('search-value-change', 'debug');
        await flushPromises();

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.searchTerm).toBe('debug');
        expect(wrapper.vm.integrationCriteria.term).toBe('debug');
        expect(dataTable.props('searchValue')).toBe('debug');
    });

    it('should filter integrations by permission', async () => {
        const wrapper = await createWrapper();
        const dataTable = wrapper.findComponent({ name: 'mt-data-table' });

        dataTable.vm.$emit('update:applied-filters', [
            {
                id: 'permissions',
                type: {
                    options: [
                        {
                            id: 'admin',
                        },
                    ],
                },
            },
        ]);
        await flushPromises();

        expect(wrapper.vm.appliedIntegrationFilters).toHaveLength(1);
        expect(wrapper.vm.integrationCriteria.filters).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    field: 'admin',
                    type: 'equals',
                    value: true,
                }),
            ]),
        );
    });
});
