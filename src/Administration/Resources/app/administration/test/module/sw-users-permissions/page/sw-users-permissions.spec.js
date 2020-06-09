import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/page/sw-users-permissions';

describe('modules/sw-users-permissions/page/sw-users-permissions', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-users-permissions'), {
            provide: {},
            mocks: {},
            stubs: {
                'sw-page': '<div><slot name="content"></slot></div>',
                'sw-card-view': true,
                'sw-settings-user-list': true
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should contain the user list', () => {
        const userListComponent = wrapper.find('sw-settings-user-list-stub');

        expect(userListComponent.exists()).toBeTruthy();
    });
});
