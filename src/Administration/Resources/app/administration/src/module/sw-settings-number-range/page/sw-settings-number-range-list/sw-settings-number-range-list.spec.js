/**
 * @sw-package framework
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
                        meta: {
                            $module: {
                                icon: 'regular-content',
                            },
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
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
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
                    'sw-ignore-class': true,
                    'sw-entity-listing': {
                        props: [
                            'items',
                            'dataSource',
                        ],
                        template: `
                    <div>
                        <template v-for="item in (dataSource || items)">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`,
                    },
                    'sw-language-switch': true,
                    'sw-search-bar': true,
                    'sw-context-menu-item': true,
                    'sw-loader': true,
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

    it('Should not allow create without permission', async () => {
        const addButton = wrapper.find('.sw-number-range-list__add-number-range');

        expect(addButton.attributes('disabled')).toBeDefined();
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

    it('should offer the create action in the empty state when no number range exists', async () => {
        wrapper = await createWrapper([
            'number_ranges.creator',
        ]);
        await flushPromises();

        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-settings-number-range.list.messageEmpty');

        const createButton = wrapper.find('.mt-empty-state__button .mt-button');

        expect(createButton.exists()).toBe(true);
        expect(createButton.attributes('disabled')).toBeUndefined();
    });

    it('should not offer the create action when a search has no hits', async () => {
        wrapper = await createWrapper([
            'number_ranges.creator',
        ]);
        await flushPromises();
        await wrapper.setData({ term: 'zzzqqqnothing' });

        // a search without hits is not an empty number range list, so it offers no create action
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('.mt-empty-state__button').exists()).toBe(false);
    });
});
