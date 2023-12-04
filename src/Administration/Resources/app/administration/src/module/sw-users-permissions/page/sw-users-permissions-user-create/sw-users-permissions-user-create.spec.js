/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils';
import TimezoneService from 'src/core/service/timezone.service';
import EntityCollection from 'src/core/data/entity-collection.data';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-users-permissions-user-create', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            provide: {
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    },
                },
                loginService: {},
                userService: {
                    getUser: () => Promise.resolve({ data: {} }),
                },
                userValidationService: {},
                integrationService: {},
                repositoryFactory: {
                    create: (entityName) => {
                        if (entityName === 'user') {
                            return {
                                search: () => Promise.resolve(),
                                get: () => {
                                    return Promise.resolve(
                                        {
                                            localeId: '7dc07b43229843d387bb5f59233c2d66',
                                            username: 'admin',
                                            firstName: '',
                                            lastName: 'admin',
                                            email: 'info@shopware.com',
                                        },
                                    );
                                },
                                create: () => {
                                    return {
                                        localeId: '',
                                        username: '',
                                        firstName: '',
                                        lastName: '',
                                        email: '',
                                        password: '',
                                    };
                                },
                            };
                        }

                        if (entityName === 'language') {
                            return {
                                search: () => Promise.resolve(new EntityCollection(
                                    '',
                                    '',
                                    Shopware.Context.api,
                                    null,
                                    [],
                                    0,
                                )),
                                get: () => Promise.resolve(),
                            };
                        }

                        return {};
                    },
                },


            },
            mocks: {
                $route: {
                    params: {
                        id: '1a2b3c4d',
                    },
                },
            },
            stubs: {
                'sw-page': {
                    template: '<div><slot name="content"></slot></div>',
                },
                'sw-card-view': true,
                'sw-card': true,
                'sw-text-field': true,
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-password-field': {
                    template: `
                        <input type="password" :value="value" @input="$emit('update:value', $event.target.value)">
                    `,
                    props: ['value'],
                },
                'sw-select-field': true,
                'sw-switch-field': true,
                'sw-entity-multi-select': true,
                'sw-single-select': true,
                'sw-skeleton': true,
                'sw-empty-state': true,
                'sw-data-grid': true,
                'sw-button': true,
                'sw-context-menu-item': true,
            },
        },
    });
}
// TODO: fix these tests and add test cases
describe('modules/sw-users-permissions/page/sw-users-permissions-user-create', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Service().register('timezoneService', () => {
            return new TimezoneService();
        });
    });

    beforeEach(async () => {
        Shopware.State.get('session').languageId = '123456789';
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        Shopware.State.get('session').languageId = '';
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should create a new user', async () => {
        expect(wrapper.vm.user).toStrictEqual({
            admin: false,
            localeId: '',
            username: '',
            firstName: '',
            lastName: '',
            email: '',
            password: '',
        });
    });

    it('should allow to set the password', async () => {
        await wrapper.setData({ isLoading: false });
        expect(wrapper.vm.user.password).toBe('');

        const fieldPassword = wrapper.find('.sw-settings-user-detail__grid-password');
        await fieldPassword.setValue('Passw0rd!');
        await flushPromises();

        expect(wrapper.vm.user.password).toBe('Passw0rd!');
    });

    it('should not be an admin by default', async () => {
        await wrapper.setData({ isLoading: false });
        const adminSwitch = wrapper.find('.sw-settings-user-detail__grid-is-admin');

        expect(adminSwitch.attributes().value).toBeUndefined();
        expect(wrapper.vm.user.admin).toBe(false);
    });
});
