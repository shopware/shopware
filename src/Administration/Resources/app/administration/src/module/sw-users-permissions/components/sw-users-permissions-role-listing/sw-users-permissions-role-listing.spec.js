/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = [], isSso = { isSso: false }, deleteFunction = () => {}) {
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
                            delete: deleteFunction,
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
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
                    ssoSettingsService: {
                        isSso: () => {
                            return Promise.resolve(isSso);
                        },
                    },
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
                stubs: {
                    'sw-container': true,
                    'sw-simple-search-field': true,
                    'sw-data-grid': {
                        props: ['dataSource'],
                        template: `
<div>
    <template v-for="item in dataSource">
        <slot name="actions" v-bind="{ item }"></slot>
        <slot name="action-modals" v-bind="{ item }"></slot>
    </template>
</div>
`,
                    },
                    'sw-context-menu-item': true,
                    'sw-verify-user-modal': true,
                    'router-link': true,
                    'sw-pagination': true,
                    'sw-modal': {
                        template: `<div class="modal">
                            <slot></slot>
                            <slot name="modal-footer"></slot>
                        </div>`,
                    },
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

    it('the card should contain the right title', async () => {
        const title = wrapper.findByText('h3', 'sw-users-permissions.roles.general.cardLabel');
        expect(title.exists()).toBe(true);
    });

    it('should disable the create button', async () => {
        const createButton = wrapper.find('.sw-users-permissions-role-listing__add-role-button');
        expect(createButton.attributes('disabled')).toBeDefined();
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

    it('should open password confirm modal', async () => {
        const deleteFunction = jest.fn().mockReturnValue(Promise.resolve());
        wrapper = await createWrapper(
            [
                'users_and_permissions.deleter',
                'users_and_permissions.editor',
            ],
            { isSso: false },
            deleteFunction,
        );

        await wrapper.setData({
            roles: [
                {
                    id: 'anyId',
                    name: 'anyName',
                },
            ],
        });

        await flushPromises();

        const contextMenuItemDelete = wrapper.find('.sw-users-permissions-role-listing__context-menu-delete');
        await contextMenuItemDelete.trigger('click');
        await flushPromises();

        const confirmButton = wrapper.find('.sw-users-permissions-role-listing__confirm-delete-button');
        await confirmButton.trigger('click');
        await flushPromises();

        expect(wrapper.find('sw-verify-user-modal-stub').exists()).toBeTruthy();
        expect(deleteFunction).not.toHaveBeenCalled();
    });

    it('should delete role without pw confirmation', async () => {
        const deleteFunction = jest.fn().mockReturnValue(Promise.resolve());
        wrapper = await createWrapper(
            [
                'users_and_permissions.deleter',
                'users_and_permissions.editor',
            ],
            { isSso: true },
            deleteFunction,
        );

        await wrapper.setData({
            roles: [
                {
                    id: 'anyId',
                    name: 'anyName',
                },
            ],
        });

        await flushPromises();

        const contextMenuItemDelete = wrapper.find('.sw-users-permissions-role-listing__context-menu-delete');
        await contextMenuItemDelete.trigger('click');
        await flushPromises();

        const confirmButton = wrapper.find('.sw-users-permissions-role-listing__confirm-delete-button');
        await confirmButton.trigger('click');
        await flushPromises();

        expect(deleteFunction).toHaveBeenCalled();
    });
});
