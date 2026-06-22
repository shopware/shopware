/**
 * @sw-package discovery
 */

import { mount, RouterLinkStub } from '@vue/test-utils';

import swSalesChannelDetailAgenticFile from './index';

Shopware.Component.register('sw-sales-channel-detail-agentic-file', swSalesChannelDetailAgenticFile);

const discoveredFiles = [
    {
        fileFamily: 'agentic',
        fileName: 'llms.txt',
        templatePath: 'files/agentic/llms.txt.twig',
        contentType: 'text/plain; charset=utf-8',
        supportsUserProvidedContent: true,
        templates: [
            {
                twigNamespace: 'Framework',
                templateName: '@Framework/files/agentic/llms.txt.twig',
                templateContent: '{% block agentic_llms_txt %}Shopware llms template{% endblock %}',
                role: 'base',
            },
            {
                twigNamespace: 'Ucp',
                templateName: '@Ucp/files/agentic/llms.txt.twig',
                templateContent: '{% sw_extends "@Framework/files/agentic/llms.txt.twig" %}',
                role: 'extension',
            },
        ],
        configuration: {
            id: 'configured-file-id',
            enabled: true,
            templateOverrides: {
                Framework: 'custom llms text',
            },
        },
    },
    {
        fileFamily: 'agentic',
        fileName: 'agents.md',
        templatePath: 'files/agentic/agents.md.twig',
        contentType: 'text/markdown; charset=utf-8',
        supportsUserProvidedContent: true,
        templates: [
            {
                twigNamespace: 'Framework',
                templateName: '@Framework/files/agentic/agents.md.twig',
                templateContent: '{% block agentic_agents_md %}Shopware agents template{% endblock %}',
                role: 'base',
            },
        ],
        configuration: null,
    },
];

function cloneDiscoveredFiles() {
    return JSON.parse(JSON.stringify(discoveredFiles));
}

async function createWrapper(options = {}) {
    const salesChannel = Object.hasOwn(options, 'salesChannel')
        ? options.salesChannel
        : {
              id: 'sales-channel-id',
              accessKey: 'sales-channel-access-key',
              typeId: Shopware.Defaults.storefrontSalesChannelTypeId,
              domains: [
                  {
                      url: 'https://shop.example.com/storefront/',
                  },
              ],
          };
    const routeFileName = options.routeFileName ?? 'llms.txt';
    const salesChannelFileApiService = {
        detail: jest.fn(async (_fileFamily, _salesChannelId, requestedFileName) => {
            const file = cloneDiscoveredFiles().find((item) => item.fileName === requestedFileName) ?? null;

            return options.serviceResponse ?? { data: file };
        }),
        preview: jest.fn(
            async () =>
                options.previewResponse ?? {
                    fileName: 'llms.txt',
                    contentType: 'text/plain; charset=utf-8',
                    content: '# Demo shop\nUse public catalog pages.',
                },
        ),
    };
    const wrapper = mount(swSalesChannelDetailAgenticFile, {
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card" :class="$attrs.class"><slot name="title"></slot><slot name="headerRight"></slot><slot name="action"></slot><slot></slot><slot name="grid"></slot></div>',
                    props: [
                        'positionIdentifier',
                        'isLoading',
                    ],
                },
                'mt-button': {
                    template:
                        '<button class="mt-button" v-bind="$attrs" :disabled="disabled" @click="$emit(\'click\')"><slot></slot></button>',
                    emits: [
                        'click',
                    ],
                    props: [
                        'size',
                        'variant',
                        'disabled',
                        'square',
                    ],
                },
                'mt-icon': {
                    template: '<span class="mt-icon" :data-name="name"><slot></slot></span>',
                    props: [
                        'name',
                        'size',
                    ],
                },
                'mt-textarea': {
                    template: `
                            <textarea
                                class="mt-textarea"
                                :value="modelValue"
                                @input="$emit('update:modelValue', $event.target.value)"
                            ></textarea>
                        `,
                    props: [
                        'modelValue',
                        'name',
                        'label',
                        'placeholder',
                        'disabled',
                    ],
                },
                'sw-modal': {
                    template:
                        '<div class="sw-modal" :class="$attrs.class"><h2>{{ title }}</h2><slot></slot><slot name="modal-footer"></slot></div>',
                    emits: [
                        'modal-close',
                    ],
                    props: [
                        'title',
                        'variant',
                    ],
                },
                'sw-code-editor': {
                    template: `
                            <textarea
                                class="sw-code-editor"
                                :value="value"
                                @input="$emit('update:value', $event.target.value)"
                            ></textarea>
                        `,
                    emits: [
                        'update:value',
                    ],
                    props: [
                        'value',
                        'name',
                        'mode',
                        'softWraps',
                        'setFocus',
                        'label',
                    ],
                },
                'router-link': RouterLinkStub,
                'sw-label': {
                    template:
                        '<span class="sw-label" :data-appearance="appearance" :data-size="size" :data-variant="variant"><slot></slot></span>',
                    props: [
                        'appearance',
                        'size',
                        'variant',
                    ],
                },
                'sw-data-grid': {
                    template: `
                            <div class="sw-data-grid">
                                <div
                                    v-for="item in dataSource"
                                    :key="item.id"
                                    class="sw-data-grid__row"
                                >
                                    <slot name="column-templateName" v-bind="{ item }"></slot>
                                    <slot name="column-role" v-bind="{ item }"></slot>
                                    <slot name="actions" v-bind="{ item }"></slot>
                                </div>
                            </div>
                        `,
                    props: [
                        'identifier',
                        'dataSource',
                        'columns',
                        'showSelection',
                        'showActions',
                        'plainAppearance',
                    ],
                },
                'sw-context-menu-item': {
                    template:
                        '<button class="sw-context-menu-item" :disabled="disabled" @click="$emit(\'click\')"><slot></slot></button>',
                    emits: [
                        'click',
                    ],
                    props: [
                        'disabled',
                        'routerLink',
                        'variant',
                    ],
                },
                'mt-empty-state': {
                    template: '<div class="mt-empty-state">{{ headline }}</div>',
                    props: [
                        'headline',
                        'icon',
                    ],
                },
            },
            provide: {
                salesChannelFileApiService,
                repositoryFactory: {
                    create: () => ({
                        create: () => ({
                            id: 'new-sales-channel-file-id',
                        }),
                    }),
                },
            },
            mocks: {
                $route: {
                    params: {
                        id: 'sales-channel-id',
                        fileName: routeFileName,
                    },
                },
                $te: (key) => key.includes('["llms.txt"]') || key.includes('["agents.md"]'),
            },
        },
        props: {
            salesChannel,
        },
    });

    return {
        wrapper,
        salesChannelFileApiService,
    };
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-agentic-file', () => {
    it('loads the selected file and generated preview', async () => {
        const { wrapper, salesChannelFileApiService } = await createWrapper();

        await flushPromises();

        expect(salesChannelFileApiService.detail).toHaveBeenCalledWith('agentic', 'sales-channel-id', 'llms.txt');
        expect(salesChannelFileApiService.preview).toHaveBeenCalledWith('agentic', 'sales-channel-id', 'llms.txt', {
            Framework: 'custom llms text',
        });
        expect(wrapper.vm.file).toEqual(discoveredFiles[0]);
        expect(wrapper.text()).toContain('# Demo shop');
    });

    it('renders file metadata, full description and template override states', async () => {
        const { wrapper } = await createWrapper();

        await flushPromises();

        expect(wrapper.text()).toContain('llms.txt');
        expect(wrapper.text()).toContain('/llms.txt');
        expect(wrapper.text()).toContain('text/plain; charset=utf-8');
        expect(wrapper.text()).not.toContain('sw-sales-channel.detail.agenticFiles.detail.labelTemplatePath');
        expect(wrapper.text()).toContain('sw-sales-channel.detail.agenticFiles.descriptions["agentic"]["llms.txt"]');
        expect(wrapper.text()).toContain('sw-sales-channel.detail.agenticFiles.detail.showContentSources');
        expect(wrapper.text()).not.toContain('@Framework/files/agentic/llms.txt.twig');

        await wrapper.find('.sw-sales-channel-detail-agentic-file__content-sources-toggle').trigger('click');
        await flushPromises();

        expect(wrapper.vm.contentSourceTemplates).toEqual([
            expect.objectContaining({
                id: 'Framework',
                twigNamespace: 'Framework',
            }),
            expect.objectContaining({
                id: 'Ucp',
                twigNamespace: 'Ucp',
            }),
        ]);
        expect(wrapper.text()).toContain('@Framework/files/agentic/llms.txt.twig');
        expect(wrapper.text()).toContain('@Ucp/files/agentic/llms.txt.twig');
        expect(wrapper.text()).toContain('sw-sales-channel.detail.agenticFiles.detail.roleBase');
        expect(wrapper.text()).toContain('sw-sales-channel.detail.agenticFiles.detail.roleExtension');
        expect(wrapper.text()).toContain('sw-sales-channel.detail.agenticFiles.enabledState.custom');
        expect(wrapper.text()).toContain('sw-sales-channel.detail.agenticFiles.detail.hideContentSources');
        expect(wrapper.text()).toContain('sw-sales-channel.detail.agenticFiles.detail.customNotesTitle');

        const labels = wrapper.findAll('.sw-label');
        expect(labels.at(0).attributes('data-appearance')).toBe('pill');
        expect(labels.at(0).attributes('data-variant')).toBe('success');

        const publicPathPreview = wrapper.find('.sw-sales-channel-detail-agentic-file__public-path-link');
        expect(publicPathPreview.attributes('aria-label')).toBe(
            'sw-sales-channel.detail.agenticFiles.detail.actionPreviewPublicPath',
        );
        expect(publicPathPreview.text()).toBe('/llms.txt');

        expect(wrapper.findComponent(RouterLinkStub).props('to')).toEqual({
            name: 'sw.sales.channel.detail.agenticFiles',
            params: {
                id: 'sales-channel-id',
            },
        });
    });

    it('opens a source override modal from the source column and stages edits for the global save', async () => {
        const { wrapper, salesChannelFileApiService } = await createWrapper();

        await flushPromises();

        await wrapper.find('.sw-sales-channel-detail-agentic-file__content-sources-toggle').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-sales-channel-detail-agentic-file__source-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-sales-channel-detail-agentic-file__override-modal').exists()).toBe(true);
        expect(wrapper.find('.sw-sales-channel-detail-agentic-file__override-input').element.value).toBe('custom llms text');

        await wrapper.find('.sw-sales-channel-detail-agentic-file__override-input').setValue('Updated Framework override');
        await wrapper.find('.sw-sales-channel-detail-agentic-file__override-modal-apply').trigger('click');
        await flushPromises();

        const configuration = wrapper.vm.salesChannel.salesChannelFiles.find((item) => item.fileName === 'llms.txt');

        expect(configuration.templateOverrides).toEqual({
            Framework: 'Updated Framework override',
        });
        expect(salesChannelFileApiService.preview).toHaveBeenLastCalledWith('agentic', 'sales-channel-id', 'llms.txt', {
            Framework: 'Updated Framework override',
        });
    });

    it('opens a source override modal from the context menu and can reset to default content', async () => {
        const { wrapper, salesChannelFileApiService } = await createWrapper();

        await flushPromises();

        await wrapper.find('.sw-sales-channel-detail-agentic-file__content-sources-toggle').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-context-menu-item').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-sales-channel-detail-agentic-file__override-modal-reset').trigger('click');
        await flushPromises();

        const configuration = wrapper.vm.salesChannel.salesChannelFiles.find((item) => item.fileName === 'llms.txt');

        expect(wrapper.find('.sw-sales-channel-detail-agentic-file__override-input').element.value).toBe(
            '{% block agentic_llms_txt %}Shopware llms template{% endblock %}',
        );
        expect(configuration.templateOverrides).toEqual({});
        expect(salesChannelFileApiService.preview).toHaveBeenLastCalledWith('agentic', 'sales-channel-id', 'llms.txt', {});
    });

    it('keeps the default template when applying unchanged default content', async () => {
        const { wrapper, salesChannelFileApiService } = await createWrapper({
            routeFileName: 'agents.md',
        });

        await flushPromises();

        await wrapper.find('.sw-sales-channel-detail-agentic-file__content-sources-toggle').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-sales-channel-detail-agentic-file__source-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-sales-channel-detail-agentic-file__override-input').element.value).toBe(
            '{% block agentic_agents_md %}Shopware agents template{% endblock %}',
        );

        await wrapper.find('.sw-sales-channel-detail-agentic-file__override-modal-apply').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-sales-channel-detail-agentic-file__override-modal').exists()).toBe(false);
        expect(wrapper.vm.salesChannel.salesChannelFiles).toBeUndefined();
        expect(salesChannelFileApiService.preview).toHaveBeenCalledTimes(1);
        expect(salesChannelFileApiService.preview).toHaveBeenLastCalledWith('agentic', 'sales-channel-id', 'agents.md', {});
    });

    it('links the public path to the first configured sales channel domain', async () => {
        const { wrapper } = await createWrapper();

        await flushPromises();

        const publicPathPreview = wrapper.find('.sw-sales-channel-detail-agentic-file__public-path-link');

        expect(wrapper.vm.publicPreviewUrl).toBe('https://shop.example.com/storefront/llms.txt');
        expect(publicPathPreview.attributes('href')).toBe('https://shop.example.com/storefront/llms.txt');
        expect(publicPathPreview.attributes('target')).toBe('_blank');
        expect(publicPathPreview.attributes('rel')).toBe('noopener noreferrer');
    });

    it('disables the public path preview when the file is disabled', async () => {
        const { wrapper } = await createWrapper({
            routeFileName: 'agents.md',
        });

        await flushPromises();

        expect(wrapper.vm.publicPreviewUrl).toBeNull();
        expect(wrapper.find('.sw-sales-channel-detail-agentic-file__public-path-link').exists()).toBe(false);
        expect(wrapper.find('.sw-sales-channel-detail-agentic-file__public-path-disabled').text()).toBe(
            'sw-sales-channel.detail.agenticFiles.detail.actionEnablePublicPathPreview',
        );
    });

    it('does not link the public path for non-storefront sales channels', async () => {
        const { wrapper } = await createWrapper({
            salesChannel: {
                id: 'sales-channel-id',
                accessKey: 'headless-access-key',
                typeId: Shopware.Defaults.apiSalesChannelTypeId,
                domains: [],
            },
        });

        await flushPromises();

        expect(wrapper.vm.publicPreviewUrl).toBeNull();
        expect(wrapper.find('.sw-sales-channel-detail-agentic-file__public-path-link').exists()).toBe(false);
        expect(wrapper.find('.sw-sales-channel-detail-agentic-file__public-path-disabled').text()).toBe('/llms.txt');
    });

    it('toggles the enabled state from the detail page', async () => {
        const { wrapper } = await createWrapper();

        await flushPromises();

        await wrapper.find('.mt-button').trigger('click');
        await flushPromises();

        expect(wrapper.vm.file.configuration.enabled).toBe(false);
    });

    it('stores custom notes on the sales channel file association for the global save', async () => {
        const { wrapper } = await createWrapper({
            routeFileName: 'agents.md',
        });

        await flushPromises();

        await wrapper
            .find('.sw-sales-channel-detail-agentic-file__custom-notes-input')
            .setValue('Ask before starting checkout.');
        await flushPromises();

        const configuration = wrapper.vm.salesChannel.salesChannelFiles.find((item) => item.fileName === 'agents.md');

        expect(configuration.templateOverrides).toEqual({
            user_provided_content: 'Ask before starting checkout.',
        });
    });

    it('shows an empty state when the route does not match a discovered file', async () => {
        const { wrapper, salesChannelFileApiService } = await createWrapper({
            routeFileName: 'missing.txt',
        });

        await flushPromises();

        expect(salesChannelFileApiService.preview).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('sw-sales-channel.detail.agenticFiles.detail.emptyState');
    });

    it('supports route params for files in subfolders', async () => {
        const { wrapper } = await createWrapper({
            routeFileName: [
                '.well-known',
                'agents.json',
            ],
        });

        expect(wrapper.vm.routeFileName).toBe('.well-known/agents.json');
    });
});
