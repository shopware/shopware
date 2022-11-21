import { shallowMount } from '@vue/test-utils';
import swUsersPermissions from 'src/module/sw-users-permissions/page/sw-users-permissions';

Shopware.Component.register('sw-users-permissions', swUsersPermissions);

describe('modules/sw-users-permissions/page/sw-users-permissions', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = shallowMount(await Shopware.Component.build('sw-users-permissions'), {
            stubs: {
                'sw-page': {
                    template: '<div><slot name="content"></slot></div>'
                },
                'sw-card-view': true,
                'sw-users-permissions-user-listing': true,
                'sw-users-permissions-role-listing': true
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the user list', async () => {
        const userListComponent = wrapper.find('sw-users-permissions-user-listing-stub');

        expect(userListComponent.exists()).toBeTruthy();
    });
});
