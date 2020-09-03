import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/components/sw-users-permissions-role-listing';

describe('module/sw-users-permissions/components/sw-users-permissions-role-listing', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-users-permissions-role-listing'), {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([])
                    })
                }
            },
            mocks: {
                $tc: v => v,
                $router: { replace: () => {} },
                $route: { query: '' }
            },
            stubs: {
                'sw-card': true,
                'sw-container': true,
                'sw-simple-search-field': true,
                'sw-button': true,
                'sw-empty-state': true
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('the card should contain the right title', () => {
        const title = wrapper.attributes().title;
        expect(title).toBe('sw-users-permissions.roles.general.cardLabel');
    });
});
