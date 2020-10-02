import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-users-permissions/page/sw-users-permissions-user-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {
        bind(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        },
        inserted(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        },
        update(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        }
    });

    return shallowMount(Shopware.Component.build('sw-users-permissions-user-detail'), {
        localVue,
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
            },
            feature: {
                isActive: () => true
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
            'sw-page': {
                template: '<div><slot name="content"></slot></div>'
            },
            'sw-card-view': true,
            'sw-card': {
                template: `
    <div class="sw-card-stub">
        <slot></slot>
        <slot name="grid"></slot>
    </div>
    `
            },
            'sw-text-field': true,
            'sw-upload-listener': true,
            'sw-media-upload-v2': true,
            'sw-password-field': true,
            'sw-select-field': true,
            'sw-switch-field': true,
            'sw-entity-multi-select': true,
            'sw-data-grid': {
                props: ['dataSource'],
                template: `
<div>
  <template v-for="item in dataSource">
      <slot name="actions" v-bind="{ item }"></slot>
  </template>
</div>
                `
            },
            'sw-context-menu-item': true
        }
    });
}

describe('modules/sw-users-permissions/page/sw-users-permissions-user-detail', () => {
    let wrapper;

    beforeEach(async () => {
        Shopware.State.get('session').languageId = '123456789';
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        await wrapper.destroy();
        Shopware.State.get('session').languageId = '';
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
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
        await wrapper.setData({
            user: {
                localeId: '12345',
                username: 'maxmuster',
                firstName: 'Max',
                lastName: 'Mustermann',
                email: 'max@mustermann.com'
            }
        });

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

    it('should enable the tooltip warning when user is admin', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        await wrapper.setData({
            user: {
                admin: true,
                localeId: '12345',
                username: 'maxmuster',
                firstName: 'Max',
                lastName: 'Mustermann',
                email: 'max@mustermann.com'
            }
        });

        const aclRolesSelect = wrapper.find('.sw-settings-user-detail__grid-aclRoles');

        expect(aclRolesSelect.attributes()['data-tooltip-message'])
            .toBe('sw-users-permissions.users.user-detail.disabledRoleSelectWarning');

        expect(aclRolesSelect.attributes()['data-tooltip-disabled']).toBe('false');
    });

    it('should disable the tooltip warning when user is not admin', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        await wrapper.setData({
            user: {
                admin: false,
                localeId: '12345',
                username: 'maxmuster',
                firstName: 'Max',
                lastName: 'Mustermann',
                email: 'max@mustermann.com'
            }
        });

        const aclRolesSelect = wrapper.find('.sw-settings-user-detail__grid-aclRoles');

        expect(aclRolesSelect.attributes()['data-tooltip-disabled']).toBe('true');
    });

    it('should disable all fields when user has not editor rights', async () => {
        await wrapper.setData({
            isLoading: false,
            user: {
                admin: false,
                localeId: '12345',
                username: 'maxmuster',
                firstName: 'Max',
                lastName: 'Mustermann',
                email: 'max@mustermann.com'
            },
            integrations: [
                {}
            ]
        });

        const fieldFirstName = wrapper.find('.sw-settings-user-detail__grid-firstName');
        const fieldLastName = wrapper.find('.sw-settings-user-detail__grid-lastName');
        const fieldEmail = wrapper.find('.sw-settings-user-detail__grid-eMail');
        const fieldUsername = wrapper.find('.sw-settings-user-detail__grid-username');
        const fieldProfilePicture = wrapper.find('.sw-settings-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.find('.sw-settings-user-detail__grid-password');
        const fieldLanguage = wrapper.find('.sw-settings-user-detail__grid-language');
        const contextMenuItemEdit = wrapper.find('.sw-settings-user-detail__grid-context-menu-edit');
        const contextMenuItemDelete = wrapper.find('.sw-settings-user-detail__grid-context-menu-delete');

        expect(fieldFirstName.attributes().disabled).toBe('true');
        expect(fieldLastName.attributes().disabled).toBe('true');
        expect(fieldEmail.attributes().disabled).toBe('true');
        expect(fieldUsername.attributes().disabled).toBe('true');
        expect(fieldProfilePicture.attributes().disabled).toBe('true');
        expect(fieldPassword.attributes().disabled).toBe('true');
        expect(fieldLanguage.attributes().disabled).toBe('true');
        expect(contextMenuItemEdit.attributes().disabled).toBe('true');
        expect(contextMenuItemDelete.attributes().disabled).toBe('true');
    });

    it('should enable all fields when user has not editor rights', async () => {
        wrapper = createWrapper('users_and_permissions.editor');

        await wrapper.setData({
            isLoading: false,
            user: {
                admin: false,
                localeId: '12345',
                username: 'maxmuster',
                firstName: 'Max',
                lastName: 'Mustermann',
                email: 'max@mustermann.com'
            },
            integrations: [
                {}
            ]
        });

        const fieldFirstName = wrapper.find('.sw-settings-user-detail__grid-firstName');
        const fieldLastName = wrapper.find('.sw-settings-user-detail__grid-lastName');
        const fieldEmail = wrapper.find('.sw-settings-user-detail__grid-eMail');
        const fieldUsername = wrapper.find('.sw-settings-user-detail__grid-username');
        const fieldProfilePicture = wrapper.find('.sw-settings-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.find('.sw-settings-user-detail__grid-password');
        const fieldLanguage = wrapper.find('.sw-settings-user-detail__grid-language');
        const contextMenuItemEdit = wrapper.find('.sw-settings-user-detail__grid-context-menu-edit');
        const contextMenuItemDelete = wrapper.find('.sw-settings-user-detail__grid-context-menu-delete');

        expect(fieldFirstName.attributes().disabled).toBeUndefined();
        expect(fieldLastName.attributes().disabled).toBeUndefined();
        expect(fieldEmail.attributes().disabled).toBeUndefined();
        expect(fieldUsername.attributes().disabled).toBeUndefined();
        expect(fieldProfilePicture.attributes().disabled).toBeUndefined();
        expect(fieldPassword.attributes().disabled).toBeUndefined();
        expect(fieldLanguage.attributes().disabled).toBeUndefined();
        expect(contextMenuItemEdit.attributes().disabled).toBeUndefined();
        expect(contextMenuItemDelete.attributes().disabled).toBeUndefined();
    });
});
