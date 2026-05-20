/**
 * @sw-package fundamentals@framework
 */
import { mount, flushPromises } from '@vue/test-utils';

const mockRole = {
    id: 'test-role-id',
    name: 'Test Role',
    description: '',
    isNew: jest.fn(() => true),
};

const repositoryFactory = {
    create: jest.fn(() => ({
        search: jest.fn(() => Promise.resolve([])),
    })),
};

async function createWrapper(privileges = [], options = {}) {
    return mount(
        await wrapTestComponent('sw-users-permissions-role-view-general', {
            sync: true,
        }),
        {
            props: {
                role: options.role ?? mockRole,
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'mt-banner': true,
                    'mt-textarea': true,
                    'sw-text-field': true,
                    'mt-number-field': true,
                    'sw-users-permissions-permissions-grid': true,
                    'sw-users-permissions-additional-permissions': true,
                    'sw-users-permissions-role-mcp-reference-modal': true,
                },
                provide: {
                    repositoryFactory: options.repositoryFactory ?? repositoryFactory,
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                },
            },
        },
    );
}

describe('module/sw-users-permissions/view/sw-users-permissions-role-view-general', () => {
    it('should disable the button and fields when no aclPrivileges exists', async () => {
        const wrapper = await createWrapper();

        const fieldRoleName = wrapper.find('input[aria-label="sw-users-permissions.roles.detail.labelName"]');
        const fieldRoleDescription = wrapper.find(
            'mt-textarea-stub[label="sw-users-permissions.roles.detail.labelDescription"]',
        );
        const permissionsGrid = wrapper.find('sw-users-permissions-permissions-grid-stub');
        const additionalPermissionsGrid = wrapper.find('sw-users-permissions-additional-permissions-stub');

        expect(fieldRoleName.attributes().disabled).toBeDefined();
        expect(fieldRoleDescription.attributes().disabled).toBe('true');
        expect(permissionsGrid.attributes().disabled).toBe('true');
        expect(additionalPermissionsGrid.attributes().disabled).toBe('true');
    });

    describe('mcp hint visibility', () => {
        const savedRole = { ...mockRole, isNew: jest.fn(() => false) };

        function repositoryFactoryReturning(integrations) {
            return {
                create: jest.fn(() => ({
                    search: jest.fn(() => Promise.resolve(integrations)),
                })),
            };
        }

        it('shows the hint when an integration restricts only resources (tools=null, resources=[])', async () => {
            const wrapper = await createWrapper(['users_and_permissions.editor'], {
                role: savedRole,
                repositoryFactory: repositoryFactoryReturning([
                    { id: 'int-1', mcpAllowlist: { tools: null, resources: [], prompts: null } },
                ]),
            });
            await flushPromises();

            expect(wrapper.vm.shouldShowMcpHint).toBe(true);
        });

        it('shows the hint when an integration restricts only prompts (tools=null, prompts=[])', async () => {
            const wrapper = await createWrapper(['users_and_permissions.editor'], {
                role: savedRole,
                repositoryFactory: repositoryFactoryReturning([
                    { id: 'int-1', mcpAllowlist: { tools: null, resources: null, prompts: [] } },
                ]),
            });
            await flushPromises();

            expect(wrapper.vm.shouldShowMcpHint).toBe(true);
        });

        it('shows the hint when an integration restricts tools (legacy case)', async () => {
            const wrapper = await createWrapper(['users_and_permissions.editor'], {
                role: savedRole,
                repositoryFactory: repositoryFactoryReturning([
                    { id: 'int-1', mcpAllowlist: { tools: ['x'], resources: null, prompts: null } },
                ]),
            });
            await flushPromises();

            expect(wrapper.vm.shouldShowMcpHint).toBe(true);
        });

        it('hides the hint when no integration has any restriction', async () => {
            const wrapper = await createWrapper(['users_and_permissions.editor'], {
                role: savedRole,
                repositoryFactory: repositoryFactoryReturning([
                    { id: 'int-1', mcpAllowlist: { tools: null, resources: null, prompts: null } },
                ]),
            });
            await flushPromises();

            expect(wrapper.vm.shouldShowMcpHint).toBe(false);
        });
    });

    it('should enable the button and fields when edit aclPrivileges exists', async () => {
        const wrapper = await createWrapper(['users_and_permissions.editor']);

        const fieldRoleName = wrapper.find('input[aria-label="sw-users-permissions.roles.detail.labelName"]');
        const fieldRoleDescription = wrapper.find(
            'mt-textarea-stub[label="sw-users-permissions.roles.detail.labelDescription"]',
        );
        const permissionsGrid = wrapper.find('sw-users-permissions-permissions-grid-stub');
        const additionalPermissionsGrid = wrapper.find('sw-users-permissions-additional-permissions-stub');

        expect(fieldRoleName.attributes().disabled).toBeUndefined();
        expect(fieldRoleDescription.attributes().disabled).toBeUndefined();
        expect(permissionsGrid.attributes().disabled).toBeUndefined();
        expect(additionalPermissionsGrid.attributes().disabled).toBeUndefined();
    });
});
