/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-integration/page/sw-integration-list';

async function createWrapper(privileges = []) {
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
                            return Promise.resolve([
                                {
                                    id: '44de136acf314e7184401d36406c1e90',
                                },
                            ]);
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
                },

                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
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
                'sw-card': {
                    template: `
                        <div class="sw-card">
                            <slot></slot>
                        </div>
                        `,
                },
                'sw-language-switch': true,
                'sw-search-bar': true,
                'sw-icon': true,
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-password-field': await wrapTestComponent('sw-password-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-field-error': true,
                'sw-field-copyable': true,
                'sw-switch-field': true,
                'sw-entity-multi-select': true,
                'sw-empty-state': {
                    template: '<div class="sw-empty-state"></div>',
                },
                'sw-entity-listing': {
                    props: [
                        'items',
                        'detailRoute',
                    ],
                    template: `
                        <div>
                            <template v-for="item in items" :key="item.id">
                                <slot name="actions" v-bind="{ item }">
                                </slot>
                                <slot name="action-modals" v-bind="{ item }">
                                </slot>
                            </template>
                        </div>
                    `,
                },
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-alert': true,
                'sw-label': true,
                'router-link': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
    });

    await flushPromises();
    return wrapper;
}

describe('module/sw-integration/page/sw-integration-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

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

        const deleteButton = wrapper.find('.sw-modal .sw-button--primary');
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

        const adminRoleSwitch = wrapper.find('.sw-settings-user-detail__grid-is-admin');
        expect(adminRoleSwitch.attributes().disabled).toBeDefined();
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
                    field: 'app.id',
                    type: 'equals',
                    value: null,
                }),
            ]),
        );
    });
});
