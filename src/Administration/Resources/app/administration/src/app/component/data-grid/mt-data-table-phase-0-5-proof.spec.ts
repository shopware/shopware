/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import MtDataTable from '@shopware-ag/meteor-component-library/dist/esm/MtDataTable';

jest.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) => key,
    }),
}));

class ResizeObserverMock {
    observe() {
        return undefined;
    }

    unobserve() {
        return undefined;
    }

    disconnect() {
        return undefined;
    }
}

global.ResizeObserver = ResizeObserverMock as typeof ResizeObserver;

type TableRow = {
    id: string;
    [key: string]: unknown;
};

type BuiltInRenderer = 'text' | 'number' | 'price' | 'badge';
type BadgeVariant = 'default' | 'warning' | 'critical' | 'positive' | 'info';

type ProbeColumn = {
    label: string;
    property: string;
    renderer: BuiltInRenderer;
    position: number;
    sortable?: boolean;
    allowResize?: boolean;
    visible?: boolean;
    clickable?: boolean;
    previewImage?: string;
    rendererOptions?: {
        renderItemBadge?: (
            data: unknown,
            columnDefinition: ProbeColumn,
        ) => {
            label: string;
            variant: BadgeVariant;
        };
        currencyId?: string;
        currencyISOCode?: string;
        source?: 'gross' | 'net';
    };
};

type ProbeCandidate = {
    sourceComponent: string;
    columns: ProbeColumn[];
    dataSource: TableRow[];
    expectedTexts: string[];
};

const supportedRenderers: BuiltInRenderer[] = [
    'text',
    'number',
    'price',
    'badge',
];

const candidateColumnSets: ProbeCandidate[] = [
    {
        sourceComponent: 'sw-settings-logging-list',
        columns: [
            {
                label: 'sw-settings-logging.list.columnDate',
                property: 'createdAt',
                renderer: 'text',
                position: 0,
                allowResize: true,
            },
            {
                label: 'sw-settings-logging.list.columnMessage',
                property: 'message',
                renderer: 'text',
                position: 100,
                allowResize: true,
            },
            {
                label: 'sw-settings-logging.list.columnLevel',
                property: 'level',
                renderer: 'badge',
                position: 200,
                allowResize: true,
                rendererOptions: {
                    renderItemBadge(data: unknown) {
                        const level = (data as { level: number }).level;

                        return {
                            label: `Warning (${level})`,
                            variant: 'warning',
                        };
                    },
                },
            },
            {
                label: 'sw-settings-logging.list.columnContent',
                property: 'context',
                renderer: 'text',
                position: 300,
                allowResize: true,
                clickable: true,
            },
        ],
        dataSource: [
            {
                id: 'log-entry-1',
                createdAt: '2026-06-11T10:12:00.000Z',
                message: 'checkout.order.placed',
                level: 300,
                context: '{"orderId":"order-1"}',
            },
        ],
        expectedTexts: [
            '2026-06-11T10:12:00.000Z',
            'checkout.order.placed',
            'Warning (300)',
            '{"orderId":"order-1"}',
        ],
    },
    {
        sourceComponent: 'sw-flow-list-flow-templates',
        columns: [
            {
                label: 'sw-flow.list.labelColumnName',
                property: 'name',
                renderer: 'text',
                position: 0,
                allowResize: false,
                clickable: true,
            },
            {
                label: 'sw-flow.list.labelColumnDescription',
                property: 'config.description',
                renderer: 'text',
                position: 100,
                allowResize: false,
                sortable: false,
            },
            {
                label: 'sw-flow.template.create',
                property: 'createFlow',
                renderer: 'text',
                position: 200,
                allowResize: false,
                sortable: false,
                clickable: true,
            },
        ],
        dataSource: [
            {
                id: 'flow-template-1',
                name: 'Order confirmation',
                config: {
                    description: 'Send a mail when an order is placed.',
                },
                createFlow: 'sw-flow.template.create',
            },
        ],
        expectedTexts: [
            'Order confirmation',
            'Send a mail when an order is placed.',
            'sw-flow.template.create',
        ],
    },
    {
        sourceComponent: 'sw-generic-custom-entity-list',
        columns: [
            {
                label: 'custom_entity.list.name',
                property: 'name',
                renderer: 'text',
                position: 0,
                visible: true,
                clickable: true,
            },
            {
                label: 'custom_entity.list.technicalName',
                property: 'technicalName',
                renderer: 'text',
                position: 100,
                visible: true,
            },
            {
                label: 'custom_entity.list.translatedTitle',
                property: 'translated.title',
                renderer: 'text',
                position: 200,
                visible: true,
            },
        ],
        dataSource: [
            {
                id: 'custom-entity-1',
                name: 'Landing page teaser',
                technicalName: 'landing_page_teaser',
                translated: {
                    title: 'Summer campaign',
                },
            },
        ],
        expectedTexts: [
            'Landing page teaser',
            'landing_page_teaser',
            'Summer campaign',
        ],
    },
];

function createWrapper(candidate: ProbeCandidate) {
    return mount(MtDataTable, {
        attachTo: document.body,
        props: {
            dataSource: candidate.dataSource,
            columns: candidate.columns,
            currentPage: 1,
            paginationLimit: 25,
            paginationTotalItems: candidate.dataSource.length,
            disableSearch: true,
            disableSettingsTable: true,
            disableEdit: true,
            disableDelete: true,
        },
        global: {
            mocks: {
                $t: (key: string) => key,
            },
            stubs: {
                'mt-icon': true,
                teleport: true,
            },
        },
    });
}

describe('mt-data-table Phase 0.5 renderer proof', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it.each(candidateColumnSets)('renders $sourceComponent with built-in renderers only', async (candidate) => {
        const wrapper = createWrapper(candidate);
        await nextTick();

        expect(candidate.columns.every((column) => supportedRenderers.includes(column.renderer))).toBe(true);
        expect(wrapper.findAll('[data-cell-column-property]')).toHaveLength(
            candidate.columns.length * candidate.dataSource.length,
        );

        candidate.expectedTexts.forEach((expectedText) => {
            expect(wrapper.text()).toContain(expectedText);
        });

        wrapper.unmount();
    });

    it('emits only the generic open-details event for clickable built-in cells', async () => {
        const wrapper = createWrapper(candidateColumnSets[1]);
        await nextTick();

        await wrapper.find('a.mt-data-table-text-renderer').trigger('click');

        const openDetailsEvents = wrapper.emitted('open-details') ?? [];

        expect(openDetailsEvents.length).toBeGreaterThan(0);
        openDetailsEvents.forEach(([payload]) => {
            expect(payload).toEqual(candidateColumnSets[1].dataSource[0]);
        });
        expect(wrapper.emitted('context-select')).toBeUndefined();

        wrapper.unmount();
    });

    it('renders the built-in text preview image option without a custom cell slot', async () => {
        const wrapper = createWrapper({
            sourceComponent: 'text-renderer-preview-image-capability',
            columns: [
                {
                    label: 'Preview',
                    property: 'name',
                    renderer: 'text',
                    position: 0,
                    previewImage: 'media.url',
                },
            ],
            dataSource: [
                {
                    id: 'preview-row-1',
                    name: 'Preview row',
                    media: {
                        url: '/administration/static/img/preview-row.png',
                    },
                },
            ],
            expectedTexts: ['Preview row'],
        });
        await nextTick();

        const previewImage = wrapper.find('img.mt-data-table-preview-image-renderer-item');

        expect(previewImage.attributes('src')).toBe('/administration/static/img/preview-row.png');
        expect(previewImage.attributes('alt')).toBe('Preview row');

        wrapper.unmount();
    });
});
