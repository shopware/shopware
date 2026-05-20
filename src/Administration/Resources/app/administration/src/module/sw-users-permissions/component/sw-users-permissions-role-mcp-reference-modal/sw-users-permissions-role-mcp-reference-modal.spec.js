/**
 * @sw-package fundamentals@framework
 */
import { mount, flushPromises } from '@vue/test-utils';

const mockTools = [
    {
        name: 'shopware-system-config-read',
        description: 'Read system config',
        requiredPrivileges: { static: ['system_config:read'], entityParam: null, operations: [] },
    },
    {
        name: 'shopware-entity-read',
        description: 'Read entities',
        requiredPrivileges: { static: [], entityParam: 'entity', operations: ['read'] },
    },
    {
        name: 'shopware-entity-upsert',
        description: 'Write entities',
        requiredPrivileges: {
            static: [],
            entityParam: 'entity',
            operations: [
                'create',
                'update',
            ],
        },
    },
    {
        name: 'merchant-product-create',
        description: 'Create products',
        requiredPrivileges: {
            static: [
                'product:create',
                'product:read',
                'tax:read',
            ],
            entityParam: null,
            operations: [],
        },
    },
];

const mcpToolService = {
    getTools: jest.fn(() => Promise.resolve(mockTools)),
};

function makeRole(privileges = ['system_config:read']) {
    return { id: 'role-id', privileges: [...privileges] };
}

async function createWrapper(options = {}) {
    const role = options.role ?? makeRole();

    const mcpIntegrations = options.mcpIntegrations ?? [
        {
            id: 'int-1',
            mcpAllowlist: {
                tools: [
                    'shopware-system-config-read',
                    'shopware-entity-read',
                ],
                resources: null,
                prompts: null,
            },
        },
    ];

    return mount(await wrapTestComponent('sw-users-permissions-role-mcp-reference-modal', { sync: true }), {
        props: { role, mcpIntegrations },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-modal': { template: '<div class="sw-modal"><slot /><slot name="modal-footer" /></div>' },
                'mt-loader': true,
                'mt-empty-state': true,
                'mt-banner': { template: '<div class="mt-banner"><slot /></div>' },
                'mt-button': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
            },
            provide: { mcpToolService },
        },
    });
}

describe('module/sw-users-permissions/component/sw-users-permissions-role-mcp-reference-modal', () => {
    beforeEach(() => {
        mcpToolService.getTools.mockClear();
    });

    // ── Row rendering ──────────────────────────────────────────────────────────

    it('default view (by-permission) groups by entity prefix', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const labels = wrapper.findAll('.sw-users-permissions-role-mcp-reference-modal__row-label').map((el) => el.text());

        expect(labels).toContain('system_config');
        expect(labels).not.toContain('shopware-system-config-read');
    });

    it('by-tool view shows one row per tool', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.viewMode = 'tool';
        await wrapper.vm.$nextTick();

        const labels = wrapper.findAll('.sw-users-permissions-role-mcp-reference-modal__row-label').map((el) => el.text());

        expect(labels).toContain('shopware-system-config-read');
        expect(labels).toContain('shopware-entity-read');
        expect(labels).not.toContain('shopware-entity-upsert');
    });

    it('marks a granted static privilege with is--granted class', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const grantedTexts = wrapper
            .findAll('.sw-users-permissions-role-mcp-reference-modal__privilege-chip.is--granted')
            .map((el) => el.text());

        expect(grantedTexts).toContain('system_config:read');
    });

    it('marks a missing static privilege with is--missing class', async () => {
        const wrapper = await createWrapper({ role: makeRole([]) });
        await flushPromises();

        const missingTexts = wrapper
            .findAll('.sw-users-permissions-role-mcp-reference-modal__privilege-chip.is--missing')
            .map((el) => el.text());

        expect(missingTexts).toContain('system_config:read');
    });

    it('renders dynamic privilege chips with is--dynamic class', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const dynamicTexts = wrapper
            .findAll('.sw-users-permissions-role-mcp-reference-modal__privilege-chip.is--dynamic')
            .map((el) => el.text());

        expect(dynamicTexts).toContain('<entity>:read');
    });

    it('shows empty state when no tools match the allowlist', async () => {
        const wrapper = await createWrapper({
            mcpIntegrations: [
                { id: 'int-1', mcpAllowlist: { tools: ['nonexistent-tool'], resources: null, prompts: null } },
            ],
        });
        await flushPromises();

        expect(wrapper.find('mt-empty-state-stub').exists()).toBe(true);
    });

    // ── Allowlist union ────────────────────────────────────────────────────────

    it('unions allowlists across multiple integrations', async () => {
        const wrapper = await createWrapper({
            mcpIntegrations: [
                { id: 'int-1', mcpAllowlist: { tools: ['shopware-system-config-read'], resources: null, prompts: null } },
                { id: 'int-2', mcpAllowlist: { tools: ['shopware-entity-read'], resources: null, prompts: null } },
            ],
        });
        await flushPromises();

        wrapper.vm.viewMode = 'tool';
        await wrapper.vm.$nextTick();

        const labels = wrapper.findAll('.sw-users-permissions-role-mcp-reference-modal__row-label').map((el) => el.text());

        expect(labels).toContain('shopware-system-config-read');
        expect(labels).toContain('shopware-entity-read');
    });

    it('treats integrations with tools=null as "all tools allowed"', async () => {
        const wrapper = await createWrapper({
            mcpIntegrations: [
                { id: 'int-1', mcpAllowlist: { tools: null, resources: [], prompts: null } },
            ],
        });
        await flushPromises();

        wrapper.vm.viewMode = 'tool';
        await wrapper.vm.$nextTick();

        const labels = wrapper.findAll('.sw-users-permissions-role-mcp-reference-modal__row-label').map((el) => el.text());

        expect(labels).toContain('shopware-system-config-read');
        expect(labels).toContain('shopware-entity-read');
        expect(labels).toContain('shopware-entity-upsert');
    });

    it('shows all tools when one integration is unrestricted alongside a restricted one', async () => {
        const wrapper = await createWrapper({
            mcpIntegrations: [
                { id: 'int-1', mcpAllowlist: { tools: ['shopware-system-config-read'], resources: null, prompts: null } },
                { id: 'int-2', mcpAllowlist: { tools: null, resources: [], prompts: null } },
            ],
        });
        await flushPromises();

        wrapper.vm.viewMode = 'tool';
        await wrapper.vm.$nextTick();

        const labels = wrapper.findAll('.sw-users-permissions-role-mcp-reference-modal__row-label').map((el) => el.text());

        expect(labels).toContain('shopware-entity-upsert');
    });

    // ── Grant actions ──────────────────────────────────────────────────────────

    it('grantPrivilege adds dot-format privilege to role.privileges', async () => {
        const role = makeRole([]);
        const wrapper = await createWrapper({ role });
        await flushPromises();

        wrapper.vm.grantPrivilege('system_config:read');

        expect(role.privileges).toContain('system_config.viewer');
    });

    it('grantPrivilege auto-adds viewer when granting editor', async () => {
        const role = makeRole([]);
        const wrapper = await createWrapper({ role });
        await flushPromises();

        wrapper.vm.grantPrivilege('product:update');

        expect(role.privileges).toContain('product.editor');
        expect(role.privileges).toContain('product.viewer');
    });

    it('grantPrivilege does not duplicate already-present privileges', async () => {
        const role = makeRole(['system_config.viewer']);
        const wrapper = await createWrapper({ role });
        await flushPromises();

        wrapper.vm.grantPrivilege('system_config:read');

        expect(role.privileges.filter((p) => p === 'system_config.viewer')).toHaveLength(1);
    });

    it('grantPrivilege ignores dynamic chips', async () => {
        const role = makeRole([]);
        const wrapper = await createWrapper({ role });
        await flushPromises();

        wrapper.vm.grantPrivilege('<entity>:read');

        expect(role.privileges).toHaveLength(0);
    });

    it('grantAllMissing grants every missing static privilege', async () => {
        const wrapper = await createWrapper({
            role: makeRole([]),
            mcpIntegrations: [
                {
                    id: 'int-1',
                    mcpAllowlist: {
                        tools: [
                            'shopware-system-config-read',
                            'merchant-product-create',
                        ],
                        resources: null,
                        prompts: null,
                    },
                },
            ],
        });
        await flushPromises();

        wrapper.vm.grantAllMissing();

        expect(wrapper.vm.role.privileges).toContain('system_config.viewer');
        expect(wrapper.vm.role.privileges).toContain('product.creator');
        expect(wrapper.vm.role.privileges).toContain('product.viewer');
        expect(wrapper.vm.role.privileges).toContain('tax.viewer');
    });

    it('grantRow grants only missing privileges in that specific row', async () => {
        const role = makeRole(['product.viewer']);
        const wrapper = await createWrapper({
            role,
            mcpIntegrations: [
                { id: 'int-1', mcpAllowlist: { tools: ['merchant-product-create'], resources: null, prompts: null } },
            ],
        });
        await flushPromises();

        // By-permission mode groups by entity: [product, tax] alphabetically
        // Grant only the product row
        const productRow = wrapper.vm.displayRows.find((r) => r.label === 'product');
        wrapper.vm.grantRow(productRow);

        // product.creator granted; product.viewer already present (not duplicated); tax not touched
        expect(role.privileges).toContain('product.creator');
        expect(role.privileges.filter((p) => p === 'product.viewer')).toHaveLength(1);
        expect(role.privileges).not.toContain('tax.viewer');
    });

    // ── hasPreselected state ───────────────────────────────────────────────────

    it('sets hasPreselected after grantPrivilege', async () => {
        const wrapper = await createWrapper({ role: makeRole([]) });
        await flushPromises();

        expect(wrapper.vm.hasPreselected).toBe(false);
        wrapper.vm.grantPrivilege('system_config:read');
        expect(wrapper.vm.hasPreselected).toBe(true);
    });

    it('resets hasPreselected on closeModal', async () => {
        const wrapper = await createWrapper({ role: makeRole([]) });
        await flushPromises();

        wrapper.vm.grantPrivilege('system_config:read');
        expect(wrapper.vm.hasPreselected).toBe(true);

        wrapper.vm.closeModal();
        expect(wrapper.vm.hasPreselected).toBe(false);
    });

    it('emits modal-close on closeModal', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.closeModal();

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    // ── allMissingStatic ───────────────────────────────────────────────────────

    it('allMissingStatic returns unique missing static chips across all tools', async () => {
        const wrapper = await createWrapper({
            role: makeRole([]),
            mcpIntegrations: [
                {
                    id: 'int-1',
                    mcpAllowlist: {
                        tools: [
                            'shopware-system-config-read',
                            'merchant-product-create',
                        ],
                        resources: null,
                        prompts: null,
                    },
                },
            ],
        });
        await flushPromises();

        expect(wrapper.vm.allMissingStatic).toContain('system_config:read');
        expect(wrapper.vm.allMissingStatic).toContain('product:create');
        expect(wrapper.vm.allMissingStatic).toContain('product:read');
        expect(wrapper.vm.allMissingStatic).toContain('tax:read');
        expect(new Set(wrapper.vm.allMissingStatic).size).toBe(wrapper.vm.allMissingStatic.length);
    });

    it('allMissingStatic excludes already-granted privileges', async () => {
        const wrapper = await createWrapper({
            role: makeRole(['system_config.viewer']),
        });
        await flushPromises();

        expect(wrapper.vm.allMissingStatic).not.toContain('system_config:read');
    });
});
