/**
 * @sw-package discovery
 */

import { mount, RouterLinkStub } from '@vue/test-utils';

const discoveredFiles = [
    {
        fileFamily: 'agentic',
        fileName: 'llms.txt',
        contentType: 'text/plain; charset=utf-8',
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
        contentType: 'text/markdown; charset=utf-8',
        configuration: null,
    },
];

function cloneDiscoveredFiles() {
    return JSON.parse(JSON.stringify(discoveredFiles));
}

async function createWrapper(options = {}) {
    const { serviceResponse, translations = {} } = options;
    const mergedTranslations = {
        'sw-sales-channel.detail.agenticFiles.descriptions["agentic"]["llms.txt"]': 'A Markdown index for AI assistants.',
        'sw-sales-channel.detail.agenticFiles.descriptions["agentic"]["agents.md"]':
            'Context and operating guidance for agent clients.',
        ...translations,
    };
    const salesChannel = Object.hasOwn(options, 'salesChannel')
        ? options.salesChannel
        : {
              id: 'sales-channel-id',
          };
    const salesChannelFileApiService = {
        list: jest.fn(async () => serviceResponse ?? { data: cloneDiscoveredFiles() }),
    };

    const wrapper = mount(
        await wrapTestComponent('sw-sales-channel-detail-agentic-files', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'mt-card': {
                        template:
                            '<div class="mt-card"><slot name="title"></slot><slot></slot><slot name="grid"></slot></div>',
                        props: [
                            'title',
                            'isLoading',
                        ],
                    },
                    'sw-data-grid': {
                        template: `
                            <div class="sw-data-grid">
                                <div
                                    v-for="item in dataSource"
                                    :key="item.fileName"
                                    class="sw-data-grid__row"
                                >
                                    <slot name="column-fileName" v-bind="{ item }"></slot>
                                    <slot name="column-enabled" v-bind="{ item }"></slot>
                                    <slot name="column-description" v-bind="{ item }"></slot>
                                    <slot name="actions" v-bind="{ item }"></slot>
                                </div>
                                <slot name="pagination"></slot>
                            </div>
                        `,
                        props: [
                            'dataSource',
                            'columns',
                            'showSelection',
                            'showActions',
                            'plainAppearance',
                            'isLoading',
                        ],
                    },
                    'sw-label': {
                        template:
                            '<span class="sw-label" :data-appearance="appearance" :data-size="size" :data-variant="variant"><slot></slot></span>',
                        props: [
                            'appearance',
                            'size',
                            'variant',
                        ],
                    },
                    'sw-pagination': {
                        template:
                            '<button class="sw-pagination" @click="$emit(\'page-change\', { page: 2, limit })"><slot></slot></button>',
                        props: [
                            'page',
                            'limit',
                            'steps',
                            'total',
                            'totalVisible',
                            'autoHide',
                        ],
                    },
                    'mt-icon': {
                        template: '<span class="mt-icon" :data-name="name"><slot></slot></span>',
                        props: [
                            'name',
                            'size',
                            'color',
                        ],
                    },
                    'sw-context-menu-item': {
                        template: `
                            <router-link
                                v-if="routerLink"
                                class="sw-context-menu-item"
                                :to="routerLink"
                            >
                                <slot></slot>
                            </router-link>
                            <button
                                v-else
                                class="sw-context-menu-item"
                                :disabled="disabled"
                                @click="$emit('click')"
                            >
                                <slot></slot>
                            </button>
                        `,
                        props: [
                            'disabled',
                            'routerLink',
                        ],
                    },
                    'router-link': RouterLinkStub,
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
                        },
                    },
                    $t: (key) => mergedTranslations[key] ?? key,
                    $te: (key) => Object.hasOwn(mergedTranslations, key),
                },
            },
            props: {
                salesChannel,
            },
        },
    );

    return {
        wrapper,
        salesChannelFileApiService,
    };
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-agentic-files', () => {
    it('loads agentic files for the active sales channel', async () => {
        const { wrapper, salesChannelFileApiService } = await createWrapper();

        await flushPromises();

        expect(salesChannelFileApiService.list).toHaveBeenCalledWith('agentic', 'sales-channel-id');
        expect(wrapper.vm.files).toEqual(discoveredFiles);
    });

    it('renders discovered files with enabled state and description', async () => {
        const { wrapper } = await createWrapper();

        await flushPromises();

        expect(wrapper.text()).toContain('llms.txt');
        expect(wrapper.text()).toContain('agents.md');
        expect(wrapper.text()).toContain('/llms.txt');
        expect(wrapper.text()).toContain('/agents.md');
        expect(wrapper.text()).toContain('A Markdown index for AI assistants.');
        expect(wrapper.text()).toContain('Context and operating guidance for agent clients.');
        expect(wrapper.text()).toContain('sw-sales-channel.detail.agenticFiles.description');

        const labels = wrapper.findAll('.sw-label');
        expect(labels.at(0).attributes('data-appearance')).toBe('pill');
        expect(labels.at(0).attributes('data-variant')).toBe('success');
        expect(labels.at(0).text()).toBe('sw-sales-channel.detail.agenticFiles.enabledState.enabled');
        expect(labels.at(1).attributes('data-appearance')).toBe('pill');
        expect(labels.at(1).attributes('data-variant')).toBe('info');
        expect(labels.at(1).text()).toBe('sw-sales-channel.detail.agenticFiles.enabledState.custom');
        expect(labels.at(2).attributes('data-appearance')).toBe('pill');
        expect(labels.at(2).attributes('data-variant')).toBe('neutral');
        expect(labels.at(2).text()).toBe('sw-sales-channel.detail.agenticFiles.enabledState.disabled');

        expect(wrapper.findComponent(RouterLinkStub).props('to')).toEqual({
            name: 'sw.sales.channel.detail.agenticFile',
            params: {
                id: 'sales-channel-id',
                fileName: 'llms.txt',
            },
        });
    });

    it('returns an empty description when no matching snippet exists', async () => {
        const { wrapper } = await createWrapper();

        const file = {
            fileFamily: 'agentic',
            fileName: '.well-known/unknown.json',
        };

        expect(wrapper.vm.getDisplayFileName(file)).toBe('unknown.json');
        expect(wrapper.vm.getPublicPath(file)).toBe('/.well-known/unknown.json');
        expect(wrapper.vm.getDescriptionSnippetKey(file)).toBe(
            'sw-sales-channel.detail.agenticFiles.descriptions["agentic"][".well-known/unknown.json"]',
        );
        expect(wrapper.vm.getDescription(file)).toBe('');
    });

    it('renders only the first description sentence in the table', async () => {
        const { wrapper } = await createWrapper({
            translations: {
                'sw-sales-channel.detail.agenticFiles.descriptions["agentic"]["llms.txt"]':
                    'First sentence. Second sentence.',
            },
        });

        await flushPromises();

        expect(wrapper.find('.sw-sales-channel-detail-agentic-files__description-cell').text()).toBe('First sentence.');
        expect(wrapper.text()).not.toContain('Second sentence.');
    });

    it('does not request files before a sales channel is available', async () => {
        const { salesChannelFileApiService } = await createWrapper({
            salesChannel: null,
        });

        await flushPromises();

        expect(salesChannelFileApiService.list).not.toHaveBeenCalled();
    });

    it('stages enabled state changes for the global save', async () => {
        const { wrapper } = await createWrapper();

        await flushPromises();

        wrapper.vm.onToggleEnabled(wrapper.vm.files[0]);
        await flushPromises();

        expect(wrapper.vm.files[0].configuration.enabled).toBe(false);
        expect(wrapper.vm.salesChannel.salesChannelFiles.find((item) => item.fileName === 'llms.txt').enabled).toBe(false);
    });

    it('shows edit as the first context menu action', async () => {
        const { wrapper } = await createWrapper();

        await flushPromises();

        const contextMenuItems = wrapper.findAll('.sw-context-menu-item');
        expect(contextMenuItems.at(0).text()).toBe('sw-sales-channel.detail.agenticFiles.actionEdit');

        expect(wrapper.findAllComponents(RouterLinkStub).at(1).props('to')).toEqual({
            name: 'sw.sales.channel.detail.agenticFile',
            params: {
                id: 'sales-channel-id',
                fileName: 'llms.txt',
            },
        });
    });

    it('keeps the description column flexible', async () => {
        const { wrapper } = await createWrapper();

        const descriptionColumn = wrapper.vm.columns.find((column) => column.property === 'description');

        expect(descriptionColumn.width).toBeUndefined();
    });

    it('paginates files locally', async () => {
        const files = Array.from({ length: 26 }, (_, index) => ({
            fileFamily: 'agentic',
            fileName: `file-${index}.txt`,
            contentType: 'text/plain; charset=utf-8',
            configuration: null,
        }));
        const { wrapper } = await createWrapper({
            serviceResponse: {
                data: files,
            },
        });

        await flushPromises();

        expect(wrapper.findComponent('.sw-pagination').props()).toEqual(
            expect.objectContaining({
                page: 1,
                limit: 10,
                steps: [
                    10,
                    25,
                    50,
                ],
                total: 26,
            }),
        );
        expect(wrapper.findComponent('.sw-data-grid').props('dataSource')).toHaveLength(10);

        await wrapper.find('.sw-pagination').trigger('click');

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.findComponent('.sw-data-grid').props('dataSource')).toEqual(files.slice(10, 20));
    });
});
