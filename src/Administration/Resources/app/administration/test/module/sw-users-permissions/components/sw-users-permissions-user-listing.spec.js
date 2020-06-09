import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/components/sw-users-permissions-user-listing';

describe('module/sw-users-permissions/components/sw-users-permissions-user-listing', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-users-permissions-user-listing'), {
            provide: {
                userService: {},
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
                'sw-data-grid': true
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('the data-grid should show the right columns', () => {
        const swDataGrid = wrapper.find('sw-data-grid-stub');
        expect(swDataGrid.vm.$attrs.columns).toStrictEqual([{
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
            label: 'sw-users-permissions.users.user-grid.labelRoles'
        }, {
            property: 'email',
            label: 'sw-users-permissions.users.user-grid.labelEmail'
        }]);
    });

    it('the data-grid should get the right user data', () => {
        const swDataGrid = wrapper.find('sw-data-grid-stub');
        expect(swDataGrid.vm.$attrs.dataSource).toStrictEqual([]);

        wrapper.setData({
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

        expect(swDataGrid.vm.$attrs.dataSource).toStrictEqual([{
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
});
