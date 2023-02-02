import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-users-permissions/view/sw-users-permissions-role-view-general';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-users-permissions-role-view-general'), {
        localVue,
        stubs: {
            'sw-card': true,
            'sw-field': true,
            'sw-users-permissions-permissions-grid': true,
            'sw-users-permissions-additional-permissions': true
        },
        propsData: {
            role: {}
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        }
    });
}

describe('module/sw-users-permissions/view/sw-users-permissions-role-view-general', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable the button and fields when no aclPrivileges exists', async () => {
        const wrapper = createWrapper();

        const fieldRoleName = wrapper.find('sw-field-stub[label="sw-users-permissions.roles.detail.labelName"]');
        const fieldRoleDescription = wrapper
            .find('sw-field-stub[label="sw-users-permissions.roles.detail.labelDescription"]');
        const permissionsGrid = wrapper.find('sw-users-permissions-permissions-grid-stub');
        const additionalPermissionsGrid = wrapper.find('sw-users-permissions-additional-permissions-stub');

        expect(fieldRoleName.attributes().disabled).toBe('true');
        expect(fieldRoleDescription.attributes().disabled).toBe('true');
        expect(permissionsGrid.attributes().disabled).toBe('true');
        expect(additionalPermissionsGrid.attributes().disabled).toBe('true');
    });

    it('should enable the button and fields when edit aclPrivileges exists', async () => {
        const wrapper = createWrapper(['users_and_permissions.editor']);

        const fieldRoleName = wrapper.find('sw-field-stub[label="sw-users-permissions.roles.detail.labelName"]');
        const fieldRoleDescription = wrapper
            .find('sw-field-stub[label="sw-users-permissions.roles.detail.labelDescription"]');
        const permissionsGrid = wrapper.find('sw-users-permissions-permissions-grid-stub');
        const additionalPermissionsGrid = wrapper.find('sw-users-permissions-additional-permissions-stub');

        expect(fieldRoleName.attributes().disabled).toBeUndefined();
        expect(fieldRoleDescription.attributes().disabled).toBeUndefined();
        expect(permissionsGrid.attributes().disabled).toBeUndefined();
        expect(additionalPermissionsGrid.attributes().disabled).toBeUndefined();
    });
});
