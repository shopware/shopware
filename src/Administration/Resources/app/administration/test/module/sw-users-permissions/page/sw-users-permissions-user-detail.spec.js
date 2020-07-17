import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/page/sw-users-permissions-user-detail';

describe('modules/sw-users-permissions/page/sw-users-permissions-user-detail', () => {
    let wrapper;

    beforeEach(() => {
        Shopware.State.get('session').languageId = '123456789';

        wrapper = shallowMount(Shopware.Component.build('sw-users-permissions-user-detail'), {
            provide: {
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
                $tc: v => v,
                $route: {
                    params: {
                        id: '1a2b3c4d'
                    }
                }
            },
            stubs: {
                'sw-page': '<div><slot name="content"></slot></div>',
                'sw-card-view': true,
                'sw-card': true,
                'sw-text-field': true,
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-password-field': true,
                'sw-select-field': true
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
        Shopware.State.get('session').languageId = '';
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should contain all fields', async () => {
        const fieldFirstName = wrapper.find('.sw-settings-user-detail__grid-firstName');
        const fieldLastName = wrapper.find('.sw-settings-user-detail__grid-lastName');
        const fieldEmail = wrapper.find('.sw-settings-user-detail__grid-eMail');
        const fieldUsername = wrapper.find('.sw-settings-user-detail__grid-username');
        const fieldProfilePicture = wrapper.find('.sw-settings-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.find('.sw-settings-user-detail__grid-password');
        const fieldLanguage = wrapper.find('.sw-settings-user-detail__grid-language');

        expect(fieldFirstName.exists()).toBeTruthy();
        expect(fieldLastName.exists()).toBeTruthy();
        expect(fieldEmail.exists()).toBeTruthy();
        expect(fieldUsername.exists()).toBeTruthy();
        expect(fieldProfilePicture.exists()).toBeTruthy();
        expect(fieldPassword.exists()).toBeTruthy();
        expect(fieldLanguage.exists()).toBeTruthy();

        expect(fieldFirstName.attributes('value')).toBe('');
        expect(fieldLastName.attributes('value')).toBe('admin');
        expect(fieldEmail.attributes('value')).toBe('info@shopware.com');
        expect(fieldUsername.attributes('value')).toBe('admin');
        expect(fieldProfilePicture.attributes('value')).toBe(undefined);
        expect(fieldPassword.attributes('value')).toBe(undefined);
        expect(fieldLanguage.attributes('value')).toBe('7dc07b43229843d387bb5f59233c2d66');
    });

    it('should contain all fields', async () => {
        wrapper.vm.user = {
            localeId: '12345',
            username: 'maxmuster',
            firstName: 'Max',
            lastName: 'Mustermann',
            email: 'max@mustermann.com'
        };

        const fieldFirstName = wrapper.find('.sw-settings-user-detail__grid-firstName');
        const fieldLastName = wrapper.find('.sw-settings-user-detail__grid-lastName');
        const fieldEmail = wrapper.find('.sw-settings-user-detail__grid-eMail');
        const fieldUsername = wrapper.find('.sw-settings-user-detail__grid-username');
        const fieldProfilePicture = wrapper.find('.sw-settings-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.find('.sw-settings-user-detail__grid-password');
        const fieldLanguage = wrapper.find('.sw-settings-user-detail__grid-language');

        expect(fieldFirstName.exists()).toBeTruthy();
        expect(fieldLastName.exists()).toBeTruthy();
        expect(fieldEmail.exists()).toBeTruthy();
        expect(fieldUsername.exists()).toBeTruthy();
        expect(fieldProfilePicture.exists()).toBeTruthy();
        expect(fieldPassword.exists()).toBeTruthy();
        expect(fieldLanguage.exists()).toBeTruthy();

        expect(fieldFirstName.attributes('value')).toBe('Max');
        expect(fieldLastName.attributes('value')).toBe('Mustermann');
        expect(fieldEmail.attributes('value')).toBe('max@mustermann.com');
        expect(fieldUsername.attributes('value')).toBe('maxmuster');
        expect(fieldProfilePicture.attributes('value')).toBe(undefined);
        expect(fieldPassword.attributes('value')).toBe(undefined);
        expect(fieldLanguage.attributes('value')).toBe('12345');
    });
});
