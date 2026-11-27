/**
 * @sw-package inventory
 */
import { shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

function createConfiguratorSettings(settings = []) {
    return new EntityCollection(
        'product-configurator-settings',
        '/product-configurator-settings',
        Shopware.Context.api,
        null,
        settings,
    );
}

async function createWrapper(configuratorSettings = createConfiguratorSettings()) {
    return shallowMount(
        await wrapTestComponent('sw-product-modal-variant-generation', {
            sync: true,
        }),
        {
            props: {
                groups: [],
                selectedGroups: [],
                product: {
                    id: 'parent-id',
                    configuratorSettings,
                },
            },
            global: {
                stubs: {
                    'sw-modal': {
                        template: '<div class="sw-modal"><slot /><slot name="modal-footer" /></div>',
                    },
                    'mt-button': {
                        props: { disabled: Boolean },
                        emits: ['click'],
                        template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
                    },
                },
                provide: {
                    feature: { isActive: () => false },
                    repositoryFactory: { create: () => ({ search: () => Promise.resolve([]) }) },
                    mediaService: { getDefaultFolderId: () => Promise.resolve('folder-id') },
                    searchRankingService: {
                        getSearchFieldsByEntity: () => Promise.resolve(null),
                        buildSearchQueriesForEntity: () => null,
                        isValidTerm: () => false,
                    },
                    swProductDetailLoadAll: () => {},
                },
            },
        },
    );
}

function createVariantsGenerator(calls, saveVariants) {
    const record =
        (name, result) =>
        (...args) => {
            calls.push(name);

            return result(...args);
        };

    return {
        saveConfiguratorSettings: record('saveConfiguratorSettings', () => Promise.resolve()),
        saveVariantRestrictions: record('saveVariantRestrictions', () => Promise.resolve()),
        saveVariantListingConfig: record('saveVariantListingConfig', () => Promise.resolve()),
        saveVariants: record('saveVariants', saveVariants),
    };
}

function createQueueItem() {
    return {
        id: '018f1c0a6b7e7d3c9a4e5f6a7b8c9d0e',
        productNumber: 'SW10000.1',
        type: 'digital',
        downloads: [{ id: 'media-id' }],
        options: [],
    };
}

describe('src/module/sw-product/component/sw-product-variants/sw-product-modal-variant-generation generateVariants', () => {
    beforeAll(() => {
        Shopware.Service().register('syncService', () => ({ httpClient: {} }));
        Shopware.Service().register('cacheApiService', () => ({ indexProducts() {} }));
    });

    it('should save the configurator settings, restrictions and listing config before the variants', async () => {
        const wrapper = await createWrapper();
        const calls = [];

        await wrapper.setData({
            showUploadModal: true,
            variantGenerationQueue: { createQueue: [createQueueItem()], deleteQueue: [] },
            variantsGenerator: createVariantsGenerator(calls, () => Promise.resolve()),
        });

        await wrapper.get('.sw-product-variant-generation__generate-action').trigger('click');
        await flushPromises();

        expect(calls).toEqual([
            'saveConfiguratorSettings',
            'saveVariantRestrictions',
            'saveVariantListingConfig',
            'saveVariants',
        ]);
        expect(wrapper.emitted('variations-finish-generate')).toHaveLength(1);
    });

    it('should save the same variants again when a failed generation is started again', async () => {
        const wrapper = await createWrapper();
        const saveVariants = jest
            .fn()
            .mockRejectedValueOnce(new Error('timeout of 30000ms exceeded'))
            .mockResolvedValueOnce();

        await wrapper.setData({
            showUploadModal: true,
            variantGenerationQueue: { createQueue: [createQueueItem()], deleteQueue: [] },
            variantsGenerator: createVariantsGenerator([], saveVariants),
        });

        const generateButton = wrapper.get('.sw-product-variant-generation__generate-action');
        await generateButton.trigger('click');
        await flushPromises();

        expect(wrapper.emitted('variations-finish-generate')).toBeUndefined();

        await generateButton.trigger('click');
        await flushPromises();

        const [firstAttempt, secondAttempt] = saveVariants.mock.calls.map(([queues]) => queues.createQueue);
        expect(secondAttempt).toEqual(firstAttempt);
        expect(firstAttempt).toEqual([
            expect.objectContaining({
                id: createQueueItem().id,
                downloads: [{ id: expect.any(String), mediaId: 'media-id' }],
            }),
        ]);
        expect(wrapper.emitted('variations-finish-generate')).toHaveLength(1);
    });

    it('should send only the ids of the variant options', async () => {
        const wrapper = await createWrapper();
        const saveVariants = jest.fn().mockResolvedValue();
        const queueItem = {
            ...createQueueItem(),
            options: [{ id: 'red-id', entity: { id: 'red-id', name: 'Red', group: { id: 'color-id' } } }],
        };

        await wrapper.setData({
            showUploadModal: true,
            variantGenerationQueue: { createQueue: [queueItem], deleteQueue: [] },
            variantsGenerator: createVariantsGenerator([], saveVariants),
        });

        await wrapper.get('.sw-product-variant-generation__generate-action').trigger('click');
        await flushPromises();

        const [[queues]] = saveVariants.mock.calls;
        expect(queues.createQueue[0].options).toEqual([{ id: 'red-id' }]);
    });

    it('should save the original settings without adding them to the add only selection', async () => {
        const redSetting = { id: 'red-setting-id', optionId: 'red-id', option: { id: 'red-id', groupId: 'color-id' } };
        const greenSetting = {
            id: 'green-setting-id',
            optionId: 'green-id',
            option: { id: 'green-id', groupId: 'color-id' },
        };
        const configuratorSettings = createConfiguratorSettings([redSetting]);
        const wrapper = await createWrapper(configuratorSettings);
        const savedSettingIds = [];
        const variantsGenerator = createVariantsGenerator([], () => Promise.reject(new Error('Request failed')));
        variantsGenerator.saveConfiguratorSettings = (settings) => {
            savedSettingIds.push(...settings.map((setting) => setting.id));

            return Promise.resolve();
        };

        await wrapper.setData({ isAddOnly: true });
        configuratorSettings.add(greenSetting);
        await wrapper.setData({
            showUploadModal: true,
            variantGenerationQueue: { createQueue: [createQueueItem()], deleteQueue: [] },
            variantsGenerator,
        });

        await wrapper.get('.sw-product-variant-generation__generate-action').trigger('click');
        await flushPromises();

        expect(savedSettingIds).toEqual(['red-setting-id', 'green-setting-id']);
        expect(configuratorSettings).toHaveLength(1);
        expect(configuratorSettings.get('green-setting-id')).toBe(greenSetting);
    });
});
