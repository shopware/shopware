import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/form/sw-password-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-button';
import 'src/module/sw-users-permissions/page/sw-users-permissions-user-detail';
import 'src/app/component/base/sw-button-process';

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
            },
            validationService: {}
        },
        mocks: {
            $tc: v => v,
            $route: {
                params: {
                    id: '1a2b3c4d'
                }
            },
            $device: {
                getSystemKey: () => 'STRG'
            }
        },
        stubs: {
            'sw-page': {
                template: `
<div>
    <slot name="smart-bar-actions"></slot>
    <slot name="content"></slot>
</div>`
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
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-button-process': Shopware.Component.build('sw-button-process'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-upload-listener': true,
            'sw-media-upload-v2': true,
            'sw-password-field': Shopware.Component.build('sw-text-field'),
            'sw-select-field': true,
            'sw-switch-field': true,
            'sw-entity-multi-select': true,
            'sw-icon': true,
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

        expect(fieldFirstName.props('value')).toBe('');
        expect(fieldLastName.props('value')).toBe('admin');
        expect(fieldEmail.props('value')).toBe('info@shopware.com');
        expect(fieldUsername.props('value')).toBe('admin');
        expect(fieldProfilePicture.props('value')).toBe(undefined);
        expect(fieldPassword.props('value')).toBe(undefined);
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

        expect(fieldFirstName.props('value')).toBe('Max');
        expect(fieldLastName.props('value')).toBe('Mustermann');
        expect(fieldEmail.props('value')).toBe('max@mustermann.com');
        expect(fieldUsername.props('value')).toBe('maxmuster');
        expect(fieldProfilePicture.props('value')).toBe(undefined);
        expect(fieldPassword.props('value')).toBe(undefined);
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

        expect(fieldFirstName.classes()).toContain('is--disabled');
        expect(fieldLastName.classes()).toContain('is--disabled');
        expect(fieldEmail.classes()).toContain('is--disabled');
        expect(fieldUsername.classes()).toContain('is--disabled');
        expect(fieldProfilePicture.attributes().disabled).toBe('true');
        expect(fieldPassword.classes()).toContain('is--disabled');
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

    it('should change the password', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.user.password).toBe(undefined);

        const fieldPasswordInput = wrapper.find('.sw-settings-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');

        expect(wrapper.vm.user.password).toBe('fooBar');
    });

    it('should delete the password when input is empty', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.user.password).toBe(undefined);

        const fieldPasswordInput = wrapper.find('.sw-settings-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');

        expect(wrapper.vm.user.password).toBe('fooBar');

        await fieldPasswordInput.setValue('');
        await fieldPasswordInput.trigger('change');

        expect(wrapper.vm.user.password).toBe(undefined);
    });

    it('should send a request with the new password', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.user.password).toBe(undefined);

        const fieldPasswordInput = wrapper.find('.sw-settings-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');

        expect(wrapper.vm.user.password).toBe('fooBar');
    });

    it('should not send a request when user clears the password field', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.user.password).toBe(undefined);

        const fieldPasswordInput = wrapper.find('.sw-settings-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');

        expect(wrapper.vm.user.password).toBe('fooBar');

        await fieldPasswordInput.setValue('');
        await fieldPasswordInput.trigger('change');

        expect(wrapper.vm.user.password).toBe(undefined);
    });
});
