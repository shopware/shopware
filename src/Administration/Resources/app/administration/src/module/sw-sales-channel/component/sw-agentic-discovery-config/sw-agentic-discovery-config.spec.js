/**
 * @sw-package fundamentals@framework
 */

import { mount } from '@vue/test-utils';
import swAgenticDiscoveryConfig from './index';

Shopware.Component.register('sw-agentic-discovery-config', swAgenticDiscoveryConfig);

function createConfigEntity(overrides = {}) {
    return {
        id: 'cfg-1',
        salesChannelId: 'sc-1',
        active: true,
        exposeAgentsMd: true,
        exposeLlmsTxt: true,
        exposeLlmsFullTxt: true,
        exposeAgenticSitemap: true,
        customIntro: '',
        customAgentRules: null,
        customSections: null,
        ...overrides,
    };
}

async function createWrapper({ search = [], canEdit = true } = {}) {
    const searchMock = jest.fn().mockResolvedValue({
        length: search.length,
        first: () => (search.length > 0 ? search[0] : null),
    });
    const saveMock = jest.fn().mockResolvedValue(null);
    const createMock = jest.fn(() => createConfigEntity({ id: 'new-cfg' }));

    return {
        wrapper: mount(await Shopware.Component.build('sw-agentic-discovery-config'), {
            props: {
                salesChannel: {
                    id: 'sc-1',
                    domains: { first: () => ({ url: 'https://shop.example' }) },
                },
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: searchMock,
                            save: saveMock,
                            create: createMock,
                        }),
                    },
                    acl: { can: () => canEdit },
                },
                stubs: {
                    'mt-card': { template: '<div><slot /></div>' },
                    'mt-switch': {
                        template:
                            '<input type="checkbox" :checked="modelValue" :disabled="disabled || undefined" @change="$emit(\'update:model-value\', $event.target.checked)" />',
                        props: ['modelValue', 'disabled', 'label', 'helpText'],
                    },
                    'mt-textarea': {
                        template:
                            '<textarea :value="modelValue" :disabled="disabled || undefined" @input="$emit(\'update:model-value\', $event.target.value)" />',
                        props: ['modelValue', 'disabled', 'label', 'helpText', 'placeholder'],
                    },
                    'mt-button': {
                        template: '<button :disabled="disabled || undefined" @click="$emit(\'click\', $event)"><slot /></button>',
                        props: ['disabled', 'isLoading', 'variant'],
                    },
                },
            },
        }),
        searchMock,
        saveMock,
        createMock,
    };
}

describe('sw-agentic-discovery-config', () => {
    it('loads existing config for the sales channel', async () => {
        const existing = createConfigEntity({ id: 'persisted', exposeAgentsMd: false });
        const { wrapper, searchMock } = await createWrapper({ search: [existing] });
        await flushPromises();

        expect(searchMock).toHaveBeenCalled();
        expect(wrapper.vm.config.id).toBe('persisted');
        expect(wrapper.vm.config.exposeAgentsMd).toBe(false);
    });

    it('creates a default config when none exists', async () => {
        const { wrapper, createMock } = await createWrapper({ search: [] });
        await flushPromises();

        expect(createMock).toHaveBeenCalled();
        expect(wrapper.vm.config.active).toBe(true);
        expect(wrapper.vm.config.exposeAgentsMd).toBe(true);
        expect(wrapper.vm.config.exposeLlmsTxt).toBe(true);
    });

    it('disables all inputs without sales_channel.editor privilege', async () => {
        const { wrapper } = await createWrapper({ canEdit: false });
        await flushPromises();

        const switches = wrapper.findAll('input[type="checkbox"]');
        switches.forEach((input) => {
            expect(input.attributes('disabled')).toBeDefined();
        });
    });

    it('saves the config via the repository when the save action is clicked', async () => {
        const { wrapper, saveMock } = await createWrapper({ search: [] });
        await flushPromises();

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(saveMock).toHaveBeenCalled();
    });

    it('builds preview URLs from the first sales-channel domain', async () => {
        const { wrapper } = await createWrapper({ search: [] });
        await flushPromises();

        expect(wrapper.vm.previewUrl('/agents.md')).toBe('https://shop.example/agents.md');
    });
});
