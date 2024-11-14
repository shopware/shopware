/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-users-permissions-role-listing', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve([]),
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    searchRankingService: {},
                },
                mocks: {
                    $route: { query: '' },
                },
                stubs: {
                    'sw-card': true,
                    'sw-container': true,
                    'sw-simple-search-field': true,
                    'sw-button': true,
                    'sw-empty-state': true,
                    'sw-data-grid': {
                        props: ['dataSource'],
                        template: `
<div>
    <template v-for="item in dataSource">
        <slot name="actions" v-bind="{ item }"></slot>
    </template>
</div>
`,
                    },
                    'sw-context-menu-item': true,
                    'sw-verify-user-modal': true,
                    'router-link': true,
                    'sw-pagination': true,
                },
            },
        },
    );
}

describe('module/sw-users-permissions/components/sw-users-permissions-role-listing', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('the card should contain the right title', async () => {
        const title = wrapper.attributes().title;
        expect(title).toBe('sw-users-permissions.roles.general.cardLabel');
    });

    it('should disable the create button', async () => {
        const createButton = wrapper.find('.sw-users-permissions-role-listing__add-role-button');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should enable the create button', async () => {
        wrapper = await createWrapper(['users_and_permissions.creator']);

        const createButton = wrapper.find('.sw-users-permissions-role-listing__add-role-button');
        expect(createButton.attributes().disabled).toBeUndefined();
    });

    it('should disable all context menu items', async () => {
        await wrapper.setData({
            roles: [
                {},
                {},
            ],
        });

        const contextMenuItemEdit = wrapper.find('.sw-users-permissions-role-listing__context-menu-edit');
        const contextMenuItemDelete = wrapper.find('.sw-users-permissions-role-listing__context-menu-delete');

        expect(contextMenuItemEdit.attributes().disabled).toBe('true');
        expect(contextMenuItemDelete.attributes().disabled).toBe('true');
    });

    it('should enable the edit context menu item', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor']);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            roles: [
                {},
                {},
            ],
        });

        const contextMenuItemEdit = wrapper.find('.sw-users-permissions-role-listing__context-menu-edit');
        const contextMenuItemDelete = wrapper.find('.sw-users-permissions-role-listing__context-menu-delete');

        expect(contextMenuItemEdit.attributes().disabled).toBeUndefined();
        expect(contextMenuItemDelete.attributes().disabled).toBe('true');
    });

    it('should enable the delete context menu item', async () => {
        wrapper = await createWrapper(['users_and_permissions.deleter']);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            roles: [
                {},
                {},
            ],
        });

        const contextMenuItemEdit = wrapper.find('.sw-users-permissions-role-listing__context-menu-edit');
        const contextMenuItemDelete = wrapper.find('.sw-users-permissions-role-listing__context-menu-delete');

        expect(contextMenuItemEdit.attributes().disabled).toBe('true');
        expect(contextMenuItemDelete.attributes().disabled).toBeUndefined();
    });

    it('should emit the event when listing change', async () => {
        expect(wrapper.vm).toBeTruthy();

        const emittedGetList = wrapper.emitted('get-list');
        expect(emittedGetList.length).toBeGreaterThan(0);
    });
});
