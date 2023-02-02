import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/page/sw-users-permissions';

describe('modules/sw-users-permissions/page/sw-users-permissions', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-users-permissions'), {
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
