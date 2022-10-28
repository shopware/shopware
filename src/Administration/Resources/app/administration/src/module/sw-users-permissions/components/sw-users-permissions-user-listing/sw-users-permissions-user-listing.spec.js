/**
 * @package system-settings
 */
import { shallowMount } from '@vue/test-utils';
import swUsersPermissionsUserListing from 'src/module/sw-users-permissions/components/sw-users-permissions-user-listing';
import 'src/app/component/base/sw-button';
import 'src/app/component/context-menu/sw-context-menu-item';

Shopware.Component.register('sw-users-permissions-user-listing', swUsersPermissionsUserListing);

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-users-permissions-user-listing'), {
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            userService: {},
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([]),
                    delete: () => Promise.resolve([])
                })
            },
            loginService: {},
            searchRankingService: {}
        },
        mocks: {
            $route: { query: '' }
        },
        stubs: {
            'router-link': {
                template: '<div class="router-link"><slot></slot></div>'
            },
            'sw-card': true,
            'sw-container': true,
            'sw-simple-search-field': true,
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-icon': true,
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>'
            },
            'sw-data-grid': {
                props: ['dataSource', 'columns'],
                template: `
<div class="sw-data-grid-stub">
  <template v-for="item in dataSource">
      <slot name="actions" v-bind="{ item }"></slot>
      <slot name="action-modals" v-bind="{ item }"></slot>
  </template>
</div>
`
            },
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-verify-user-modal': {
                template: '<div class="sw-verify-user-modal"></div>'
            },
        }
    });
}

describe('module/sw-users-permissions/components/sw-users-permissions-user-listing', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('the data-grid should show the right columns', async () => {
        const swDataGrid = wrapper.find('.sw-data-grid-stub');
        expect(swDataGrid.props().columns).toStrictEqual([{
            property: 'username',
            label: 'sw-users-permissions.users.user-grid.labelUsername'
        }, {
            property: 'firstName',
            label: 'sw-users-permissions.users.user-grid.labelFirstName'
        }, {
            property: 'lastName',
            label: 'sw-users-permissions.users.user-grid.labelLastName'
        }, {
            property: 'aclRoles',
            sortable: false,
            label: 'sw-users-permissions.users.user-grid.labelRoles'
        }, {
            property: 'email',
            label: 'sw-users-permissions.users.user-grid.labelEmail'
        }]);
    });

    it('the data-grid should get the right user data', async () => {
        const swDataGrid = wrapper.find('.sw-data-grid-stub');
        expect(swDataGrid.props().dataSource).toStrictEqual([]);

        await wrapper.setData({
            user: [{
                localeId: '12345',
                username: 'maxmuster',
                firstName: 'Max',
                lastName: 'Mustermann',
                email: 'max@mustermann.com'
            },
            {
                localeId: '7dc07b43229843d387bb5f59233c2d66',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com'
            }]
        });

        expect(swDataGrid.props().dataSource).toStrictEqual([{
            localeId: '12345',
            username: 'maxmuster',
            firstName: 'Max',
            lastName: 'Mustermann',
            email: 'max@mustermann.com'
        },
        {
            localeId: '7dc07b43229843d387bb5f59233c2d66',
            username: 'admin',
            firstName: '',
            lastName: 'admin',
            email: 'info@shopware.com'
        }]);
    });

    it('the card should contain the right title', async () => {
        const title = wrapper.attributes().title;
        expect(title).toBe('sw-users-permissions.users.general.cardLabel');
    });

    it('the add user button should be disabled', async () => {
        const addUser = wrapper.find('.sw-users-permissions-user-listing__add-user-button');
        expect(addUser.vm.disabled).toBe(true);
    });

    it('the add user button should be enabled', async () => {
        wrapper = await createWrapper(['users_and_permissions.creator']);
        await wrapper.vm.$nextTick();

        const addUser = wrapper.find('.sw-users-permissions-user-listing__add-user-button');
        expect(addUser.vm.disabled).toBeFalsy();
    });

    it('the context menu should be disabled', async () => {
        wrapper = await createWrapper([]);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            user: [
                {}
            ]
        });

        const contextMenuEdit = wrapper.find('.sw-settings-user-list__user-view-action');
        const contextMenuDelete = wrapper.find('.sw-settings-user-list__user-delete-action');

        expect(contextMenuEdit.vm.disabled).toBe(true);
        expect(contextMenuDelete.vm.disabled).toBe(true);
    });

    it('the context menu edit should be enabled', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor']);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            user: [
                {}
            ]
        });

        const contextMenuEdit = wrapper.find('.sw-settings-user-list__user-view-action');
        const contextMenuDelete = wrapper.find('.sw-settings-user-list__user-delete-action');

        expect(contextMenuEdit.vm.disabled).toBeFalsy();
        expect(contextMenuDelete.vm.disabled).toBe(true);
    });

    it('the context menu delete should be enabled', async () => {
        wrapper = await createWrapper(['users_and_permissions.deleter']);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            user: [
                {}
            ]
        });

        const contextMenuEdit = wrapper.find('.sw-settings-user-list__user-view-action');
        const contextMenuDelete = wrapper.find('.sw-settings-user-list__user-delete-action');

        expect(contextMenuEdit.vm.disabled).toBe(true);
        expect(contextMenuDelete.vm.disabled).toBeFalsy();
    });

    it('should open the delete modal', async () => {
        wrapper = await createWrapper(['users_and_permissions.deleter']);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            user: [
                {
                    id: 'uuid1',
                    firstName: 'John',
                    lastName: 'Doe'
                }
            ],
        });

        const contextMenuDelete = wrapper.find('.sw-settings-user-list__user-delete-action');
        await contextMenuDelete.trigger('click');

        const modal = wrapper.find('.sw-modal');
        expect(modal.exists()).toBeTruthy();

        expect(modal.attributes().title).toEqual('global.default.warning');

        const deleteModalButton = modal.findAll('.sw-button').at(1);
        expect(deleteModalButton.vm.variant).toEqual('danger');
    });

    it('should open the confirm password modal on delete', async () => {
        const user = {
            id: 'uuid1',
            firstName: 'John',
            lastName: 'Doe'
        };
        await wrapper.setData({
            user: [user],
            itemToDelete: user,
            confirmDeleteModal: true,
        });

        const modal = wrapper.find('.sw-modal');
        expect(modal.exists()).toBeTruthy();

        const deleteButton = modal.findAll('.sw-button').at(1);
        await deleteButton.trigger('click');

        const verifyUserModal = wrapper.find('.sw-verify-user-modal');
        expect(verifyUserModal.exists()).toBeTruthy();
    });

    it('should delete the user', async () => {
        Shopware.State.get('session').currentUser = {
            id: 'adminUuid'
        };

        await wrapper.setData({
            itemToDelete: {
                id: 'uuid1',
                firstName: 'John',
                lastName: 'Doe'
            },
            confirmPasswordModal: true,
        });

        const verifyUserModal = wrapper.find('.sw-verify-user-modal');
        expect(verifyUserModal.exists()).toBeTruthy();

        wrapper.vm.createNotificationSuccess = jest.fn();

        await verifyUserModal.vm.$emit('verified');

        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalled();

        wrapper.vm.createNotificationSuccess.mockRestore();
    });

    it('should not delete the current user', async () => {
        Shopware.State.get('session').currentUser = {
            id: 'adminUuid'
        };

        await wrapper.setData({
            itemToDelete: {
                id: 'adminUuid',
                firstName: 'John',
                lastName: 'Doe'
            },
            confirmPasswordModal: true,
        });

        const verifyUserModal = wrapper.find('.sw-verify-user-modal');
        expect(verifyUserModal.exists()).toBeTruthy();

        wrapper.vm.createNotificationError = jest.fn();

        await verifyUserModal.vm.$emit('verified');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();

        wrapper.vm.createNotificationError.mockRestore();
    });
});
