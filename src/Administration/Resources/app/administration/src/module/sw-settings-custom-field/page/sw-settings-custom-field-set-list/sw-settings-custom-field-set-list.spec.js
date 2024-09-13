/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';

function mockCustomFieldSetData() {
    const _customFieldSets = [];

    for (let i = 0; i < 10; i += 1) {
        const customFieldSet = {
            id: `id${i}`,
            name: `custom_additional_field_set_${i}`,
            active: true,
            apiAlias: null,
            config: {
                label: {
                    'en-GB': 'Industrial',
                },
            },
            createdAt: '2020-09-04T11:22:08.376+00:00',
            global: false,
            position: 2,
            updatedAt: '2020-09-07T07:01:50.245+00:00',
        };

        _customFieldSets.push(customFieldSet);
    }

    return _customFieldSets;
}

async function createWrapper(privileges = []) {
    const { Mixin } = Shopware;


    return mount(await wrapTestComponent('sw-settings-custom-field-set-list', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $route: {
                    params: {
                        id: '1234',
                    },
                    query: {
                        limit: '25',
                        naturalSorting: false,
                        page: 1,
                        sortBy: 'config.name',
                        sortDirection: 'ASC',
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve(mockCustomFieldSetData());
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    },
                },
                mixins: [
                    Mixin.getByName('notification'),
                    Mixin.getByName('sw-inline-snippet'),
                    Mixin.getByName('discard-detail-page-changes')('set'),
                ],
                searchRankingService: {},
            },
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
                },
                'sw-button': true,
                'sw-icon': true,
                'sw-search-bar': true,
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
                'sw-pagination': true,
                'sw-empty-state': true,
                'router-link': true,
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-card-view': true,
                'sw-ignore-class': true,
                'sw-extension-component-section': true,
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-loader': true,
                'sw-checkbox-field': true,
            },
        },
    });
}

describe('module/sw-settings-custom-field/page/sw-settings-custom-field-set-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to create a new custom-field set', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const createButton = wrapper.find('.sw-settings-custom-field-set-list__button-create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to create a new custom-field set', async () => {
        const wrapper = await createWrapper([
            'custom_field.creator',
        ]);
        await flushPromises();

        const createButton = wrapper.find('.sw-settings-custom-field-set-list__button-create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-custom-field-set-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        const wrapper = await createWrapper([
            'custom_field.deleter',
        ]);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-custom-field-set-list__delete-action');
        expect(deleteMenuItem.attributes('disabled')).toBeFalsy();
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-custom-field-set-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const wrapper = await createWrapper([
            'custom_field.editor',
        ]);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-custom-field-set-list__edit-action');
        expect(editMenuItem.attributes('disabled')).toBeFalsy();
    });

    it('should contain a listing criteria with page and limit properties', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.listingCriteria.page).toBe(1);
        expect(wrapper.vm.listingCriteria.limit).toBe(25);
    });
});
