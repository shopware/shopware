import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/page/sw-users-permissions-user-detail';
import 'src/module/sw-users-permissions/page/sw-users-permissions-user-create';
import TimezoneService from 'src/core/service/timezone.service';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-users-permissions-user-create'), {
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            loginService: {},
            userService: {
                getUser: () => Promise.resolve()
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
                                        email: 'info@shopware.com'
                                    }
                                );
                            },
                            create: () => {
                                return {
                                    localeId: '',
                                    username: '',
                                    firstName: '',
                                    lastName: '',
                                    email: '',
                                    password: ''
                                };
                            }
                        };
                    }

                    if (entityName === 'language') {
                        return {
                            search: () => Promise.resolve(),
                            get: () => Promise.resolve()
                        };
                    }

                    return {};
                }
            }


        },
        mocks: {
            $route: {
                params: {
                    id: '1a2b3c4d'
                }
            }
        },
        stubs: {
            'sw-page': {
                template: '<div><slot name="content"></slot></div>'
            },
            'sw-card-view': true,
            'sw-card': true,
            'sw-text-field': true,
            'sw-upload-listener': true,
            'sw-media-upload-v2': true,
            'sw-password-field': {
                template: '<input type="password" :value="value" @input="$emit(\'input\', $event.target.value)">',
                props: ['value']
            },
            'sw-select-field': true,
            'sw-switch-field': true,
            'sw-entity-multi-select': true,
            'sw-single-select': true
        }
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

    beforeEach(() => {
        Shopware.State.get('session').languageId = '123456789';
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
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
            password: ''
        });
    });

    it('should allow to set the password', async () => {
        expect(wrapper.vm.user.password).toBe('');

        const fieldPassword = wrapper.find('.sw-settings-user-detail__grid-password');
        await fieldPassword.setValue('Passw0rd!');

        expect(wrapper.vm.user.password).toBe('Passw0rd!');
    });

    it('should not be an admin by default', async () => {
        const adminSwitch = wrapper.find('.sw-settings-user-detail__grid-is-admin');

        expect(adminSwitch.attributes().value).toBeUndefined();
        expect(wrapper.vm.user.admin).toBe(false);
    });
});
