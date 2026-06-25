/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(
    numberRangeService = {
        previewPattern: jest.fn(() => Promise.resolve({ number: 1337 })),
        previewPatternByNumberRangeId: jest.fn(() => Promise.resolve({ number: 1337 })),
    },
    repositories = {},
) {
    const createNumberRange = (id = 'id') => ({
        description: null,
        global: true,
        id,
        name: 'Delivery notes',
        numberRangeSalesChannels: [],
        translated: {
            customFields: [],
            description: null,
            name: 'Delivery notes',
        },
        translations: [],
        type: {
            typeName: 'Delivery notes',
        },
        typeId: '72ea130130404f67a426332f7a8c7277',
    });

    const numberRangeRepository = repositories.numberRangeRepository ?? {
        create: (context, id) => createNumberRange(id),
        get: (id) => Promise.resolve(createNumberRange(id)),
        save: () => Promise.resolve(),
        search: () =>
            Promise.resolve({
                total: 1,
            }),
    };

    const numberRangeTypeRepository = repositories.numberRangeTypeRepository ?? {
        create: () => ({
            global: false,
            typeName: 'Delivery notes',
        }),
    };

    return mount(
        await wrapTestComponent('sw-settings-number-range-create', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: { params: { id: '1a' } },
                },
                provide: {
                    numberRangeService,
                    repositoryFactory: {
                        create: (entityName) => {
                            if (entityName === 'number_range') {
                                return numberRangeRepository;
                            }

                            if (entityName === 'number_range_type') {
                                return numberRangeTypeRepository;
                            }

                            return {
                                create: () => ({}),
                                search: () => Promise.resolve([]),
                            };
                        },
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions" />
                        <slot name="content" />
                        <slot />
                    </div>`,
                    },
                    'sw-button-process': {
                        template: '<div class="sw-button-process"><slot /></div>',
                        props: ['disabled'],
                    },
                    'mt-card': {
                        template: '<div class="mt-card"><slot /></div>',
                    },
                    'mt-text-field': {
                        template: '<div class="sw-field" :name="name"></div>',
                        props: [
                            'disabled',
                            'name',
                        ],
                    },
                    'sw-text-field': {
                        template: '<div class="sw-field"></div>',
                        props: ['disabled'],
                    },
                    'sw-card-view': {
                        template: '<div><slot /></div>',
                    },
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-language-info': true,
                    'sw-help-text': true,
                    'sw-multi-select': true,
                    'sw-entity-single-select': {
                        props: [
                            'value',
                            'disabled',
                        ],
                        template: `
                        <input
                           class="sw-entity-single-select"
                           value="value"
                           @change="(item) => $emit(\'update:value\', this.value, item)"
                        />
                      `,
                    },

                    'sw-skeleton': true,
                    'sw-language-switch': true,
                    'sw-custom-field-set-renderer': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-number-range/page/sw-settings-number-range-create', () => {
    it('should be has number range', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.hasProductNumberRange).toBeTruthy();
    });

    it('should be not able to filter global number range1', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            hasProductNumberRange: true,
        });

        const criteria = wrapper.vm.numberRangeTypeCriteria.filters.find((c) => c.field === 'global');
        expect(criteria.value).toBe(false);
    });

    it('should be able show product warning alert when number range is global', async () => {
        const wrapper = await createWrapper();
        const loadSalesChannelsSpy = jest.spyOn(wrapper.vm, 'loadSalesChannels');
        await wrapper.setData({ isLoading: false });
        await flushPromises();

        const selectType = wrapper.find('.sw-number-range-detail__select-type');
        await selectType.trigger('change', { technicalName: 'delivery' });
        const productAlert = wrapper.find('.sw-number_range-quickinfo__product-alert');
        expect(productAlert.exists()).toBe(false);
        expect(wrapper.vm.isShowProductWarning).toBe(false);
        expect(loadSalesChannelsSpy).toHaveBeenCalled();

        await selectType.trigger('change', { technicalName: 'product' });
        await flushPromises();
        expect(wrapper.vm.isShowProductWarning).toBe(true);
    });

    it('should hide current number and preview fields', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.showNumberRangeStateFields).toBe(false);
        expect(wrapper.find('[name="sw-field--state"]').exists()).toBe(false);
        expect(wrapper.find('[name="sw-field--preview"]').exists()).toBe(false);
    });

    it('should not preview draft number range state', async () => {
        const numberRangeService = {
            previewPattern: jest.fn(() => Promise.resolve({ number: 1337 })),
            previewPatternByNumberRangeId: jest.fn(() => Promise.resolve({ number: 1337 })),
        };

        const wrapper = await createWrapper(numberRangeService);
        await flushPromises();

        await wrapper.vm.getPreview();
        await wrapper.vm.getState();

        expect(numberRangeService.previewPattern).not.toHaveBeenCalled();
        expect(numberRangeService.previewPatternByNumberRangeId).not.toHaveBeenCalled();
    });

    it('should reload the saved number range by the generated id', async () => {
        global.activeAclRoles = ['number_ranges.editor'];

        const createNumberRange = (id = '1a') => ({
            description: null,
            global: false,
            id,
            name: 'New number range',
            numberRangeSalesChannels: [],
            pattern: '{n}',
            start: 1,
            translated: {
                customFields: [],
                description: null,
                name: 'New number range',
            },
            translations: [],
            type: {
                global: false,
                typeName: 'Customer',
            },
            typeId: '72ea130130404f67a426332f7a8c7277',
        });

        const numberRangeRepository = {
            create: jest.fn((context, id) => createNumberRange(id)),
            get: jest.fn((id) => Promise.resolve(createNumberRange(id))),
            save: jest.fn(() => Promise.resolve()),
            search: jest.fn(() => Promise.resolve({ total: 1 })),
        };

        const wrapper = await createWrapper(undefined, { numberRangeRepository });
        await flushPromises();

        await wrapper.vm.onSave();

        expect(numberRangeRepository.save).toHaveBeenCalledWith(expect.objectContaining({ id: '1a' }));
        expect(numberRangeRepository.get).toHaveBeenCalledWith('1a', Shopware.Context.api, wrapper.vm.numberRangeCriteria);
    });
});
