import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 */

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-settings-delivery-time-list', {
        sync: true,
    }), {
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
                        search: () => {
                            return Promise.resolve([
                                {
                                    id: '123abc',
                                    name: '1 - 3 weeks',
                                    min: 1,
                                    max: 3,
                                    unit: 'week',
                                },
                            ]);
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    },
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
                    </div>`,
                },
                'sw-button': true,
                'sw-icon': true,
                'sw-search-bar': true,
                'sw-language-switch': true,
                'sw-context-menu-item': true,
                'sw-card-view': true,
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-ignore-class': true,
                'sw-extension-component-section': true,
                'sw-entity-listing': {
                    props: ['items', 'allowEdit', 'allowDelete', 'detailRoute'],
                    template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }">
                                <slot name="detail-action" v-bind="{ item }">
                                    <sw-context-menu-item-stub class="sw-entity-listing__context-menu-edit-action"
                                                          v-if="detailRoute"
                                                          :disabled="!allowEdit || undefined"
                                                          :routerLink="{ name: detailRoute, params: { id: item.id } }">
                                    </sw-context-menu-item-stub>
                                </slot>

                                <slot name="delete-action" v-bind="{ item }">
                                    <sw-context-menu-item-stub :disabled="!allowDelete || undefined"
                                                          class="sw-entity-listing__context-menu-edit-delete">
                                    </sw-context-menu-item-stub>
                                </slot>
                            </slot>
                        </template>
                    </div>`,
                },
                'sw-context-menu-item-stub': true,
                'sw-loader': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
            },
        },
    });
}

describe('module/sw-settings-delivery-times/page/sw-settings-delivery-time-list', () => {
    it('should not be able to create a new delivery time if user does not have create permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-delivery-time-list__create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to create a new delivery time if user has create permission', async () => {
        const wrapper = await createWrapper(['delivery_times.creator']);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-delivery-time-list__create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit if user does not have edit permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit if user has edit permission', async () => {
        const wrapper = await createWrapper(['delivery_times.editor']);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete if user does not have delete permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-delete');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete if user has delete permission', async () => {
        const wrapper = await createWrapper(['delivery_times.deleter']);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-delete');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should hide item selection if user does not have delete permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-delivery-time-list-grid');
        expect(entityList.attributes()['show-selection']).toBeFalsy();
    });

    it('should show item selection if user has delete permission', async () => {
        const wrapper = await createWrapper(['delivery_times.deleter']);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-delivery-time-list-grid');
        expect(entityList.attributes()['show-selection']).toBeTruthy();
    });
});
