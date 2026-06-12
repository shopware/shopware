/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const set = {
    id: '9f359a2ab0824784a608fc2a443c5904',
    customFields: {},
};

let customFields = mockCustomFieldData();

function mockCustomFieldData() {
    const _customFields = [];

    for (let i = 0; i < 10; i += 1) {
        const customField = {
            id: `id${i}`,
            name: `custom_additional_field_${i}`,
            config: {
                label: { 'en-GB': `Special field ${i}` },
                customFieldType: 'checkbox',
                customFieldPosition: i + 1,
            },
        };

        _customFields.push(customField);
    }

    return _customFields;
}

function mockCustomFieldRepository(customFieldItems = customFields) {
    class Repository {
        constructor() {
            this._customFields = customFieldItems;
        }

        search() {
            const response = [...this._customFields];
            response.total = this._customFields.length;

            response.sort((a, b) => a.config.customFieldPosition - b.config.customFieldPosition);

            return Promise.resolve(response);
        }

        save(field) {
            if (field.id === 'id1337') {
                this._customFields.push(field);
            }

            return Promise.resolve();
        }

        syncDeleted() {
            this._customFields.splice(0, 1);

            return Promise.resolve();
        }
    }

    return new Repository();
}

async function createWrapper(privileges = [], repo = null) {
    customFields = mockCustomFieldData();
    repo ??= mockCustomFieldRepository();

    return mount(
        await wrapTestComponent('sw-custom-field-list', {
            sync: true,
        }),
        {
            props: {
                set: set,
            },
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create() {
                            return repo;
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
                    'mt-card': {
                        props: ['title'],
                        template: '<div class="mt-card" :data-title="title"><slot></slot></div>',
                    },
                    'mt-empty-state': {
                        props: [
                            'headline',
                            'description',
                            'icon',
                        ],
                        template: `
                            <div
                                class="mt-empty-state"
                                :data-headline="headline"
                                :data-description="description"
                                :data-icon="icon"
                            ></div>
                        `,
                    },
                    'sw-simple-search-field': {
                        template: '<div></div>',
                    },
                    'sw-container': true,
                    'sw-grid': await wrapTestComponent('sw-grid'),
                    'sw-context-button': {
                        template: '<div class="sw-context-button"><slot></slot></div>',
                    },
                    'sw-context-menu-item': {
                        template: '<div class="sw-context-menu-item"><slot></slot></div>',
                    },
                    'sw-context-menu': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-grid-column': {
                        template: '<div class="sw-grid-column"><slot></slot></div>',
                    },
                    'sw-grid-row': {
                        template: '<div class="sw-grid-row"><slot></slot></div>',
                    },
                    'sw-checkbox-field': {
                        template: '<div></div>',
                    },
                    'sw-pagination': await wrapTestComponent('sw-pagination'),
                    'sw-loader': true,
                    'sw-modal': true,
                    'sw-text-field': true,
                    'mt-number-field': true,
                    'sw-custom-field-detail': true,
                    'sw-select-field': true,
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
        },
    );
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-list/sw-custom-field-list', () => {
    it('should store api error', async () => {
        const repoMock = {
            search: jest.fn(() => Promise.resolve(mockCustomFieldRepository().search())),
            save: jest.fn(() =>
                Promise.reject({
                    response: {
                        data: {
                            errors: [
                                {
                                    code: 'SOME_ERROR_CODE',
                                    detail: 'Some error happened',
                                },
                            ],
                        },
                    },
                }),
            ),
        };

        const wrapper = await createWrapper(['custom_field.editor'], repoMock);
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.find('.sw-custom-field-list__edit-action').trigger('click');
        await flushPromises();

        expect(wrapper.find('sw-custom-field-detail-stub').exists()).toBe(true);
        await wrapper.getComponent('sw-custom-field-detail-stub').vm.$emit('custom-field-edit-save', customFields[0]);
        await flushPromises();

        expect(repoMock.save).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenNthCalledWith(1, { message: 'Some error happened' });

        const errors = Shopware.Store.get('error').getAllApiErrors();
        expect(errors).toHaveLength(1);

        const error = errors[0]?.id0?.name?.error;
        expect(error).toBeInstanceOf(Shopware.Classes.ShopwareError);
        expect(error.code).toBe('SOME_ERROR_CODE');
        expect(error.selfLink).toBe('custom_field.id0.name.error');
    });

    it('should always have a pagination', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const pagination = wrapper.find('.sw-pagination');
        expect(pagination.exists()).toBe(true);
    });

    it('should have one page initially', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const paginationButtons = wrapper.findAll('.sw-pagination__list-button');
        expect(paginationButtons).toHaveLength(1);
    });

    it('should render a custom fields empty state', async () => {
        const wrapper = await createWrapper([], mockCustomFieldRepository([]));
        await flushPromises();

        const card = wrapper.get('.sw-custom-field-list');
        const emptyState = wrapper.get('.sw-custom-field-list__empty-state');

        expect(card.attributes('data-title')).toBe('sw-settings-custom-field.set.detail.titleCardCustomFields');
        expect(emptyState.attributes('data-icon')).toBe('regular-bars-square');
        expect(emptyState.attributes('data-headline')).toBe('sw-settings-custom-field.set.detail.emptyCustomFieldsTitle');
        expect(emptyState.attributes('data-description')).toBe(
            'sw-settings-custom-field.set.detail.emptyCustomFieldsDescription',
        );
        expect(emptyState.attributes('centered')).toBeUndefined();
        expect(emptyState.attributes('role')).toBe('status');
        expect(emptyState.attributes('aria-live')).toBe('polite');
        expect(emptyState.attributes('aria-atomic')).toBe('true');
        expect(wrapper.find('.sw-custom-field-list__grid').exists()).toBeFalsy();
    });

    it('should render a search-specific custom fields empty state', async () => {
        const wrapper = await createWrapper([], mockCustomFieldRepository([]));
        await flushPromises();

        await wrapper.setData({
            term: 'does-not-exist',
        });
        await flushPromises();

        const emptyState = wrapper.get('.sw-custom-field-list__empty-state');

        expect(emptyState.attributes('data-headline')).toBe(
            'sw-settings-custom-field.set.detail.emptyCustomFieldsSearchTitle',
        );
        expect(emptyState.attributes('data-description')).toBe(
            'sw-settings-custom-field.set.detail.emptyCustomFieldsSearchDescription',
        );
        expect(emptyState.attributes('role')).toBe('status');
        expect(emptyState.attributes('aria-live')).toBe('polite');
        expect(emptyState.attributes('aria-atomic')).toBe('true');
        expect(wrapper.find('.sw-custom-field-list__grid').exists()).toBeFalsy();
    });

    it('should create new custom field', async () => {
        const wrapper = await createWrapper();

        const newCustomField = {
            id: 'id1337',
            name: 'new_field',
            config: {
                label: { 'en-GB': 'New' },
                customFieldType: 'text',
                customFieldPosition: 0,
            },
        };

        await wrapper.vm.onSaveCustomField(newCustomField);
        await flushPromises();

        // Should have two pagination buttons after add
        const paginationButtons = wrapper.findAll('.sw-pagination__list-button');
        expect(paginationButtons).toHaveLength(2);

        // Should be in grid on correct position
        const expectedRow = wrapper.findAll('.sw-grid .sw-grid__body .sw-grid-row')[0];
        expect(expectedRow.find('.sw-grid-column[data-index="label"]').text()).toBe('New');
    });

    it('should delete custom field', async () => {
        const wrapper = await createWrapper();

        const deleteCustomField = {
            id: 'id0',
            name: 'custom_additional_field_1',
            config: {
                label: { 'en-GB': 'Special field 1' },
                customFieldType: 'checkbox',
                customFieldPosition: 0,
            },
        };

        await flushPromises();

        await wrapper.setData({
            deleteCustomField: deleteCustomField,
        });

        await flushPromises();

        await wrapper.vm.onDeleteCustomField();
        await flushPromises();

        const rows = wrapper.findAll('.sw-grid .sw-grid__body .sw-grid-row');
        expect(rows).toHaveLength(9);

        const expectedRow = rows.at(0);
        expect(expectedRow.find('.sw-grid-column[data-index="label"]').text()).toBe('Special field 1');
    });

    it('should sort custom fields by position', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const customFieldPositionCells = wrapper.findAll('.sw-grid-column[data-index="position"]');
        const [
            first,
            second,
            third,
            fourth,
        ] = customFieldPositionCells;

        expect(first.text()).toBe('1');
        expect(second.text()).toBe('2');
        expect(third.text()).toBe('3');
        expect(fourth.text()).toBe('4');
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-custom-field-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const wrapper = await createWrapper([
            'custom_field.editor',
        ]);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-custom-field-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });
});
