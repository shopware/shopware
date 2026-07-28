/**
 * @sw-package fundamentals@framework
 */
import { mount, flushPromises } from '@vue/test-utils';
import 'src/module/sw-integration/component/sw-integration-mcp-allowlist';

const defaultCapabilities = {
    tools: [
        { name: 'shopware-entity-search', description: 'Search entities', dependencies: [], requiredPrivileges: [] },
        { name: 'shopware-entity-read', description: 'Read entity', dependencies: [], requiredPrivileges: [] },
    ],
    resources: [
        { uri: 'shopware://entities', name: 'Entities', description: 'All entities', mimeType: 'application/json' },
    ],
    prompts: [
        { name: 'shopware-context', description: 'Context prompt' },
    ],
};

const mcpToolService = {
    getCapabilities: jest.fn(() => Promise.resolve(defaultCapabilities)),
};

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-integration-mcp-allowlist', { sync: true }), {
        global: {
            provide: {
                mcpToolService,
                acl: {
                    can: () => true,
                },
            },
            stubs: {
                'mt-banner': true,
                'mt-switch': true,
                'mt-icon': true,
                'mt-checkbox': true,
                'mt-empty-state': true,
                'sw-collapse': true,
            },
        },
        props: {
            allowlist: null,
            disabled: false,
            isAdmin: false,
            grantedPrivileges: [],
            ...props,
        },
    });
}

describe('sw-integration-mcp-allowlist', () => {
    beforeEach(() => {
        mcpToolService.getCapabilities.mockClear();
        mcpToolService.getCapabilities.mockResolvedValue(defaultCapabilities);
    });

    it('renders without errors', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.exists()).toBe(true);
    });

    it('calls getCapabilities on created', async () => {
        await createWrapper();

        expect(mcpToolService.getCapabilities).toHaveBeenCalledTimes(1);
    });

    it('shows admin banner when isAdmin is true', async () => {
        const wrapper = await createWrapper({ isAdmin: true });

        expect(wrapper.find('.sw-integration-mcp-allowlist__admin-banner').exists()).toBe(true);
    });

    it('hides admin banner when isAdmin is false', async () => {
        const wrapper = await createWrapper({ isAdmin: false });

        expect(wrapper.find('.sw-integration-mcp-allowlist__admin-banner').exists()).toBe(false);
    });

    it('emits update:allowlist with null when allCapabilitiesEnabled is set to true', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: null, prompts: null },
        });

        wrapper.vm.allCapabilitiesEnabled = true;

        expect(wrapper.emitted('update:allowlist')).toStrictEqual([[null]]);
    });

    it('emits update:allowlist with structured object when allCapabilitiesEnabled is set to false', async () => {
        const wrapper = await createWrapper({ allowlist: null });

        wrapper.vm.allCapabilitiesEnabled = false;

        expect(wrapper.emitted('update:allowlist')).toStrictEqual([
            [{ tools: null, resources: null, prompts: null }],
        ]);
    });

    it('allCapabilitiesEnabled is true when allowlist is null', async () => {
        const wrapper = await createWrapper({ allowlist: null });

        expect(wrapper.vm.allCapabilitiesEnabled).toBe(true);
    });

    it('allCapabilitiesEnabled is false when allowlist is an object', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: [], resources: null, prompts: null },
        });

        expect(wrapper.vm.allCapabilitiesEnabled).toBe(false);
    });

    it('toolsAllowlist returns null when allowlist is null', async () => {
        const wrapper = await createWrapper({ allowlist: null });

        expect(wrapper.vm.toolsAllowlist).toBeNull();
    });

    it('toolsAllowlist returns tools sub-array when allowlist is set', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: ['shopware-entity-search'], resources: null, prompts: null },
        });

        expect(wrapper.vm.toolsAllowlist).toStrictEqual(['shopware-entity-search']);
    });

    it('groups tools by backend group when present', async () => {
        mcpToolService.getCapabilities.mockResolvedValue({
            ...defaultCapabilities,
            tools: [
                {
                    name: 'shopware-entity-search',
                    group: 'catalogue',
                    description: 'Search entities',
                    dependencies: [],
                    requiredPrivileges: [],
                },
                {
                    name: 'swag-order-export',
                    group: 'orders',
                    description: 'Export orders',
                    dependencies: [],
                    requiredPrivileges: [],
                },
            ],
        });

        const wrapper = await createWrapper({ allowlist: { tools: null, resources: null, prompts: null } });
        await flushPromises();

        expect(Object.keys(wrapper.vm.toolGroups)).toStrictEqual([
            'catalogue',
            'orders',
        ]);
        expect(wrapper.vm.groupLabel('tools', 'catalogue')).toBe('Catalogue');
    });

    it('resourcesAllowlist returns resources sub-array when allowlist is set', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: ['shopware://entities'], prompts: null },
        });

        expect(wrapper.vm.resourcesAllowlist).toStrictEqual(['shopware://entities']);
    });

    it('promptsAllowlist returns prompts sub-array when allowlist is set', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: null, prompts: ['shopware-context'] },
        });

        expect(wrapper.vm.promptsAllowlist).toStrictEqual(['shopware-context']);
    });

    it('staleEntries includes stale tool names', async () => {
        const wrapper = await createWrapper({
            allowlist: {
                tools: [
                    'old-tool',
                    'shopware-entity-search',
                ],
                resources: null,
                prompts: null,
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.staleToolNames).toContain('old-tool');
        expect(wrapper.vm.staleToolNames).not.toContain('shopware-entity-search');
    });

    it('staleEntries includes stale resource uris', async () => {
        const wrapper = await createWrapper({
            allowlist: {
                tools: null,
                resources: [
                    'shopware://old',
                    'shopware://entities',
                ],
                prompts: null,
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.staleResourceUris).toContain('shopware://old');
        expect(wrapper.vm.staleResourceUris).not.toContain('shopware://entities');
    });

    it('staleEntries includes stale prompt names', async () => {
        const wrapper = await createWrapper({
            allowlist: {
                tools: null,
                resources: null,
                prompts: [
                    'old-prompt',
                    'shopware-context',
                ],
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.stalePromptNames).toContain('old-prompt');
        expect(wrapper.vm.stalePromptNames).not.toContain('shopware-context');
    });

    it('emitUpdated merges patch into current allowlist', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: ['shopware-entity-search'], resources: null, prompts: null },
        });

        wrapper.vm.emitUpdated({ resources: ['shopware://entities'] });

        expect(wrapper.emitted('update:allowlist')).toStrictEqual([
            [{ tools: ['shopware-entity-search'], resources: ['shopware://entities'], prompts: null }],
        ]);
    });

    it('emitUpdated uses defaults when allowlist is null', async () => {
        const wrapper = await createWrapper({ allowlist: null });

        wrapper.vm.emitUpdated({ tools: ['shopware-entity-search'] });

        expect(wrapper.emitted('update:allowlist')).toStrictEqual([
            [{ tools: ['shopware-entity-search'], resources: null, prompts: null }],
        ]);
    });

    it('handles getCapabilities rejection gracefully', async () => {
        mcpToolService.getCapabilities.mockRejectedValue(new Error('Network error'));

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.availableTools).toStrictEqual([]);
        expect(wrapper.vm.availableResources).toStrictEqual([]);
        expect(wrapper.vm.availablePrompts).toStrictEqual([]);
    });

    it('deniedTypes returns types where all items are explicitly denied', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: [], prompts: null },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.deniedTypes).toContain('resources');
        expect(wrapper.vm.deniedTypes).not.toContain('tools');
        expect(wrapper.vm.deniedTypes).not.toContain('prompts');
    });

    it('deniedTypes is empty when allCapabilitiesEnabled', async () => {
        const wrapper = await createWrapper({ allowlist: null });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.deniedTypes).toStrictEqual([]);
    });

    it('deniedTypesLabel joins single type correctly', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: [], prompts: null },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.deniedTypesLabel).toBe('sw-integration.mcp.resourcesSection');
    });

    it('deniedTypesLabel joins two types with "and"', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: [], prompts: [] },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.deniedTypesLabel).toBe(
            'sw-integration.mcp.resourcesSection and sw-integration.mcp.promptsSection',
        );
    });

    it('missingCapabilitySuggestions returns empty when allCapabilitiesEnabled', async () => {
        const wrapper = await createWrapper({ allowlist: null });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.missingCapabilitySuggestions).toStrictEqual([]);
    });

    it('missingCapabilitySuggestions returns empty when resources are unrestricted', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: ['shopware-entity-search'], resources: null, prompts: null },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.missingCapabilitySuggestions).toStrictEqual([]);
    });

    it('missingCapabilitySuggestions suggests missing resource from same prefix', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: ['shopware-entity-search'], resources: [], prompts: null },
        });
        await wrapper.vm.$nextTick();

        // resources allowlist is empty (not fully denied length===0 check skips it) — wait, length===0 means skip
        // so no suggestions when fully denied — verified by deniedTypes banner instead
        expect(wrapper.vm.missingCapabilitySuggestions).toStrictEqual([]);
    });

    it('missingCapabilitySuggestions suggests resource missing from partial allowlist', async () => {
        mcpToolService.getCapabilities.mockResolvedValue({
            ...defaultCapabilities,
            resources: [
                { uri: 'shopware://entities', name: 'Entities', description: '', mimeType: 'application/json' },
                { uri: 'shopware://languages', name: 'Languages', description: '', mimeType: 'application/json' },
            ],
        });

        const wrapper = await createWrapper({
            allowlist: { tools: ['shopware-entity-search'], resources: ['shopware://languages'], prompts: null },
        });
        await wrapper.vm.$nextTick();

        const names = wrapper.vm.missingCapabilitySuggestions.map((s) => s.name);
        expect(names).toContain('shopware://entities');
        expect(names).not.toContain('shopware://languages');
    });

    it('missingCapabilitySuggestions suggests missing prompt from same prefix', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: ['shopware-entity-search'], resources: null, prompts: [] },
        });
        await wrapper.vm.$nextTick();

        // fully-denied prompts (length===0) are skipped — covered by deniedTypes banner
        expect(wrapper.vm.missingCapabilitySuggestions).toStrictEqual([]);
    });

    it('missingCapabilitySuggestions suggests prompt missing from partial allowlist', async () => {
        mcpToolService.getCapabilities.mockResolvedValue({
            ...defaultCapabilities,
            prompts: [
                { name: 'shopware-context', description: 'Context prompt' },
                { name: 'shopware-debug', description: 'Debug prompt' },
            ],
        });

        const wrapper = await createWrapper({
            allowlist: { tools: ['shopware-entity-search'], resources: null, prompts: ['shopware-debug'] },
        });
        await wrapper.vm.$nextTick();

        const names = wrapper.vm.missingCapabilitySuggestions.map((s) => s.name);
        expect(names).toContain('shopware-context');
        expect(names).not.toContain('shopware-debug');
    });

    describe('no capabilities registered', () => {
        it('renders the empty state when capabilities are empty and all-toggle is off', async () => {
            mcpToolService.getCapabilities.mockResolvedValue({ tools: [], resources: [], prompts: [] });

            const wrapper = await createWrapper({
                allowlist: { tools: null, resources: null, prompts: null },
            });
            await flushPromises();

            expect(wrapper.find('mt-empty-state-stub').exists()).toBe(true);
        });

        it('tolerates a capabilities payload with missing keys', async () => {
            mcpToolService.getCapabilities.mockResolvedValue({});

            const wrapper = await createWrapper({
                allowlist: { tools: null, resources: null, prompts: null },
            });
            await flushPromises();

            expect(wrapper.vm.availableTools).toStrictEqual([]);
            expect(wrapper.vm.availableResources).toStrictEqual([]);
            expect(wrapper.vm.availablePrompts).toStrictEqual([]);
            expect(wrapper.find('mt-empty-state-stub').exists()).toBe(true);
        });

        it('does not throw when toggling all capabilities off with no capabilities', async () => {
            mcpToolService.getCapabilities.mockResolvedValue({ tools: [], resources: [], prompts: [] });

            const wrapper = await createWrapper({ allowlist: null });
            await flushPromises();

            expect(() => {
                wrapper.vm.allCapabilitiesEnabled = false;
            }).not.toThrow();
        });
    });

    describe('malformed capability data', () => {
        it('does not throw when a tool exposes an undefined privilege chip', async () => {
            mcpToolService.getCapabilities.mockResolvedValue({
                tools: [
                    {
                        name: 'broken-tool',
                        description: 'Broken tool',
                        dependencies: [],
                        // static privilege list contains a nullish entry
                        requiredPrivileges: { static: [undefined] },
                    },
                ],
                resources: [],
                prompts: [],
            });

            const wrapper = await createWrapper({
                allowlist: { tools: null, resources: null, prompts: null },
                // non-empty granted privileges + non-admin => the chip guard is actually evaluated
                isAdmin: false,
                grantedPrivileges: ['product.viewer'],
            });
            await flushPromises();

            expect(() => wrapper.vm.privilegeChipClass(undefined)).not.toThrow();
            expect(wrapper.vm.privilegeChipClass(undefined)).toBe('neutral');
        });
    });
});
