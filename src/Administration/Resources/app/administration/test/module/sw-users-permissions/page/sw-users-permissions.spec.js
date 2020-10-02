import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/page/sw-users-permissions';

describe('modules/sw-users-permissions/page/sw-users-permissions', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-users-permissions'), {
            provide: {
                feature: {
                    isActive: () => true
                }
            },
            mocks: {},
            stubs: {
                'sw-page': {
                    template: '<div><slot name="content"></slot></div>'
                },
                'sw-card-view': true,
                'sw-settings-user-list': true,
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
        const userListComponent = wrapper.find('sw-settings-user-list-stub');

        expect(userListComponent.exists()).toBeTruthy();
    });
});
