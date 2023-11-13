/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils_v3';
import TimezoneService from 'src/core/service/timezone.service';
import EntityCollection from 'src/core/data/entity-collection.data';

let wrapper;

async function createWrapper(privileges = []) {
    wrapper = mount(await wrapTestComponent('sw-users-permissions-user-detail', {
        sync: true,
    }), {
        global: {
            directives: {
                tooltip: {
                    beforeMount(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                    mounted(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                    updated(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                },
            },
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
                                            accessKeys: {
                                                entity: 'product',
                                            },
                                        },
                                    );
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
                validationService: {},
            },
            mocks: {
                $route: {
                    params: {
                        id: '1a2b3c4d',
                    },
                },
                $device: {
                    getSystemKey: () => 'STRG',
                },
            },
            stubs: {
                'sw-page': {
                    template: `
<div>
    <slot name="smart-bar-actions"></slot>
    <slot name="content"></slot>
</div>`,
                },
                'sw-card-view': true,
                'sw-card': {
                    template: `
    <div class="sw-card-stub">
        <slot></slot>
        <slot name="grid"></slot>
    </div>
    `,
                },
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-text-field': await wrapTestComponent('sw-text-field', {
                    sync: true,
                }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-password-field': await wrapTestComponent('sw-text-field', {
                    sync: true,
                }),
                'sw-select-field': true,
                'sw-switch-field': true,
                'sw-entity-multi-select': true,
                'sw-single-select': true,
                'sw-icon': true,
                'sw-data-grid': {
                    props: ['dataSource'],
                    template: `
<div>
  <template v-for="item in dataSource">
      <slot name="actions" v-bind="{ item }"></slot>
  </template>
</div>
                `,
                },
                'sw-context-menu-item': true,
                'sw-empty-state': true,
                'sw-skeleton': true,
                'sw-loader': true,
            },
        },
    });

    // wait until all loading promises are done
    await wrapper.vm.$nextTick();

    return wrapper;
}

describe('modules/sw-users-permissions/page/sw-users-permissions-user-detail', () => {
    beforeAll(() => {
        Shopware.Service().register('timezoneService', () => {
            return new TimezoneService();
        });
    });

    beforeEach(async () => {
        Shopware.State.get('session').languageId = '123456789';
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        // Unmount need to be called here manually because the publishData cleanup does
        // not work with automatic unmount
        await wrapper.unmount();
        Shopware.State.get('session').languageId = '';
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain all fields', async () => {
        await wrapper.setData({ isLoading: false });
        await flushPromises();

        const fieldFirstName = wrapper.findComponent('.sw-settings-user-detail__grid-firstName');
        const fieldLastName = wrapper.findComponent('.sw-settings-user-detail__grid-lastName');
        const fieldEmail = wrapper.findComponent('.sw-settings-user-detail__grid-eMail');
        const fieldUsername = wrapper.findComponent('.sw-settings-user-detail__grid-username');
        const fieldProfilePicture = wrapper.findComponent('.sw-settings-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.findComponent('.sw-settings-user-detail__grid-password');
        const fieldLanguage = wrapper.findComponent('.sw-settings-user-detail__grid-language');

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
        expect(fieldProfilePicture.props('value')).toBeUndefined();
        expect(fieldPassword.props('value')).toBeUndefined();
        expect(fieldLanguage.attributes('value')).toBe('7dc07b43229843d387bb5f59233c2d66');
    });

    it('should contain all fields with a given user', async () => {
        await wrapper.setData({
            user: {
                localeId: '12345',
                username: 'maxmuster',
                firstName: 'Max',
                lastName: 'Mustermann',
                email: 'max@mustermann.com',
            },
            isLoading: false,
        });
        await flushPromises();

        const fieldFirstName = wrapper.findComponent('.sw-settings-user-detail__grid-firstName');
        const fieldLastName = wrapper.findComponent('.sw-settings-user-detail__grid-lastName');
        const fieldEmail = wrapper.findComponent('.sw-settings-user-detail__grid-eMail');
        const fieldUsername = wrapper.findComponent('.sw-settings-user-detail__grid-username');
        const fieldProfilePicture = wrapper.findComponent('.sw-settings-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.findComponent('.sw-settings-user-detail__grid-password');
        const fieldLanguage = wrapper.findComponent('.sw-settings-user-detail__grid-language');

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
        expect(fieldProfilePicture.props('value')).toBeUndefined();
        expect(fieldPassword.props('value')).toBeUndefined();
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
                email: 'max@mustermann.com',
            },
            isLoading: false,
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
                email: 'max@mustermann.com',
            },
            isLoading: false,
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
                email: 'max@mustermann.com',
            },
            integrations: [
                {},
            ],
        });
        await flushPromises();

        const fieldFirstName = wrapper.findComponent('.sw-settings-user-detail__grid-firstName');
        const fieldLastName = wrapper.findComponent('.sw-settings-user-detail__grid-lastName');
        const fieldEmail = wrapper.findComponent('.sw-settings-user-detail__grid-eMail');
        const fieldUsername = wrapper.findComponent('.sw-settings-user-detail__grid-username');
        const fieldProfilePicture = wrapper.findComponent('.sw-settings-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.findComponent('.sw-settings-user-detail__grid-password');
        const fieldLanguage = wrapper.findComponent('.sw-settings-user-detail__grid-language');
        const contextMenuItemEdit = wrapper.findComponent('.sw-settings-user-detail__grid-context-menu-edit');
        const contextMenuItemDelete = wrapper.findComponent('.sw-settings-user-detail__grid-context-menu-delete');

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
        wrapper = await createWrapper('users_and_permissions.editor');

        await wrapper.setData({
            isLoading: false,
            user: {
                admin: false,
                localeId: '12345',
                username: 'maxmuster',
                firstName: 'Max',
                lastName: 'Mustermann',
                email: 'max@mustermann.com',
            },
            integrations: [
                {},
            ],
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
        await wrapper.setData({ isLoading: false });
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();

        const fieldPasswordInput = wrapper.find('.sw-settings-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBe('fooBar');
    });

    it('should delete the password when input is empty', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        await wrapper.setData({ isLoading: false });
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();

        const fieldPasswordInput = wrapper.find('.sw-settings-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBe('fooBar');

        await fieldPasswordInput.setValue('');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();
    });

    it('should send a request with the new password', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        await wrapper.setData({ isLoading: false });
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();

        const fieldPasswordInput = wrapper.find('.sw-settings-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBe('fooBar');
    });

    it('should not send a request when user clears the password field', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        await wrapper.setData({ isLoading: false });
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();

        const fieldPasswordInput = wrapper.find('.sw-settings-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBe('fooBar');

        await fieldPasswordInput.setValue('');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();
    });
});
