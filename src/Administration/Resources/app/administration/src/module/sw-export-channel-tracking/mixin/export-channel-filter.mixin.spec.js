/**
 * @sw-package discovery
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */

import { mount } from '@vue/test-utils';

Shopware.Defaults.agenticCommerceTypeId = '5e29f9890c4d4d519a1c7f9d5c24b7c1';

import './export-channel-filter.mixin';

const mockExportChannels = [
    { id: 'ec-channel-1', name: 'OpenAI Feed' },
    { id: 'ec-channel-2', name: 'Google Feed' },
];

function createMockRepositoryFactory(exportChannels = mockExportChannels) {
    return {
        create: () => ({
            search: jest.fn().mockResolvedValue(exportChannels),
        }),
    };
}

function buildFilterList(...names) {
    return names.map((name) => ({ name }));
}

async function createWrapper(repositoryFactory = createMockRepositoryFactory()) {
    Shopware.Component.register('test-export-channel-filter-mixin', {
        template: '<div></div>',
        mixins: [Shopware.Mixin.getByName('export-channel-filter')],
        data() {
            return { defaultFilters: [] };
        },
    });

    return mount(await Shopware.Component.build('test-export-channel-filter-mixin'), {
        global: {
            provide: {
                repositoryFactory,
                filterFactory: {
                    create: jest.fn((entity, options) =>
                        Object.keys(options).map((key) => ({ name: key, ...options[key] })),
                    ),
                },
            },
            mocks: {
                $t: (key) => key,
            },
        },
    });
}

describe('export-channel-filter mixin', () => {
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
        Shopware.Component.getComponentRegistry().delete('test-export-channel-filter-mixin');
    });

    describe('loadExportChannelOptions', () => {
        it('populates exportChannelOptions from the repository', async () => {
            wrapper = await createWrapper();

            await wrapper.vm.loadExportChannelOptions();
            await flushPromises();

            expect(wrapper.vm.exportChannelOptions).toEqual(mockExportChannels);
        });

        it('fetches only Agentic Commerce type sales channels', async () => {
            const repoFactory = createMockRepositoryFactory();
            const scRepo = repoFactory.create();
            jest.spyOn(repoFactory, 'create').mockReturnValue(scRepo);

            wrapper = await createWrapper(repoFactory);
            await wrapper.vm.loadExportChannelOptions();
            await flushPromises();

            const [searchCriteria] = scRepo.search.mock.calls[0];
            const typeFilter = searchCriteria.filters.find(
                (f) => f.field === 'typeId' && f.value === Shopware.Defaults.agenticCommerceTypeId,
            );

            expect(typeFilter).toBeDefined();
        });

        it('requests sales_channel entity from repositoryFactory', async () => {
            const repoFactory = createMockRepositoryFactory();
            jest.spyOn(repoFactory, 'create');

            wrapper = await createWrapper(repoFactory);
            await wrapper.vm.loadExportChannelOptions();

            expect(repoFactory.create).toHaveBeenCalledWith('sales_channel');
        });
    });

    describe('insertExportChannelFilter', () => {
        it('inserts the filter directly after campaign-code-filter when present', async () => {
            wrapper = await createWrapper();

            const filters = buildFilterList('some-filter', 'campaign-code-filter', 'another-filter');
            wrapper.vm.insertExportChannelFilter(filters, 'order', 'label.key', 'placeholder.key');

            const campaignIndex = filters.findIndex((f) => f.name === 'campaign-code-filter');
            const exportIndex = filters.findIndex((f) => f.name === 'export-channel-filter');

            expect(exportIndex).toBe(campaignIndex + 1);
        });

        it('appends the filter to the end when campaign-code-filter is absent', async () => {
            wrapper = await createWrapper();

            const filters = buildFilterList('some-filter', 'another-filter');
            wrapper.vm.insertExportChannelFilter(filters, 'order', 'label.key', 'placeholder.key');

            expect(filters[filters.length - 1].name).toBe('export-channel-filter');
        });

        it('sets correct filter properties', async () => {
            wrapper = await createWrapper();

            const filters = [];
            wrapper.vm.insertExportChannelFilter(filters, 'order', 'label.key', 'placeholder.key');

            const filter = filters.find((f) => f.name === 'export-channel-filter');

            expect(filter.property).toBe('salesChannelTracking.salesChannelId');
            expect(filter.type).toBe('multi-select-filter');
            expect(filter.valueProperty).toBe('id');
            expect(filter.labelProperty).toBe('name');
        });

        it('passes entity and translated snippet keys to filterFactory', async () => {
            wrapper = await createWrapper();

            const filters = [];
            wrapper.vm.insertExportChannelFilter(filters, 'customer', 'my.label', 'my.placeholder');

            expect(wrapper.vm.filterFactory.create).toHaveBeenCalledWith(
                'customer',
                expect.objectContaining({ 'export-channel-filter': expect.any(Object) }),
            );

            const filter = filters.find((f) => f.name === 'export-channel-filter');
            expect(filter.label).toBe('my.label');
            expect(filter.placeholder).toBe('my.placeholder');
        });
    });
});
