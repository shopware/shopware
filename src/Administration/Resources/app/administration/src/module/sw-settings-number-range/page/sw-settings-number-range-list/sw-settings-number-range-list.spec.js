/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-number-range-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () =>
                                Promise.resolve([
                                    {
                                        global: true,
                                        id: 'id',
                                        name: 'Orders',
                                        type: {
                                            typeName: 'Orders',
                                        },
                                    },
                                ]),
                        }),
                    },
                    acl: {
                        can: (key) => (key ? privileges.includes(key) : true),
                    },
                    searchRankingService: {},
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>
                `,
                    },
                    'sw-card-view': true,
                    'sw-card': await wrapTestComponent('sw-card'),
                    'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                    'sw-ignore-class': true,
                    'sw-entity-listing': {
                        props: ['items'],
                        template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`,
                    },
                    'sw-language-switch': true,
                    'sw-search-bar': true,
                    'sw-context-menu-item': true,
                    'sw-icon': true,
                    'sw-button': true,
                    'sw-loader': true,
                    'sw-empty-state': true,
                    'sw-extension-component-section': true,
                    'sw-label': true,
                    'sw-ai-copilot-badge': true,
                    'sw-context-button': true,
                },
            },
        },
    );
}

describe('module/sw-settings-number-range/page/sw-settings-number-range-list', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('Should not allow create without permission', async () => {
        const addButton = wrapper.find('.sw-number-range-list__add-number-range');

        expect(addButton.attributes().disabled).toBe('true');
    });

    it('Should allow create with correct permission', async () => {
        wrapper = await createWrapper(['number_ranges.creator']);
        const addButton = wrapper.find('.sw-number-range-list__add-number-range');

        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should not allow edit without permission', async () => {
        await wrapper.vm.$nextTick();
        const entityListing = wrapper.find('.sw-settings-number-range-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should allow edit with edit permission', async () => {
        wrapper = await createWrapper([
            'number_ranges.editor',
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        const entityListing = wrapper.find('.sw-settings-number-range-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not allow edit without edit permission', async () => {
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should now allow delete without delete permission', async () => {
        wrapper = await createWrapper([
            'number_ranges.editor',
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete if user does not have delete permission', async () => {
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-delete');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete if user has delete permission', async () => {
        wrapper = await createWrapper([
            'number_ranges.deleter',
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-delete');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });
});
