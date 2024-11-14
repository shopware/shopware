/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
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
                    numberRangeService: {
                        previewPattern: () => Promise.resolve({ number: 1337 }),
                    },
                    repositoryFactory: {
                        create: () => ({
                            create: () =>
                                Promise.resolve({
                                    description: null,
                                    global: true,
                                    id: 'id',
                                    name: 'Delivery notes',
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
                                }),
                            search: () =>
                                Promise.resolve({
                                    total: 1,
                                }),
                        }),
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
                    'sw-button': {
                        template: '<div class="sw-button"><slot /></div>',
                        props: ['disabled'],
                    },
                    'sw-button-process': {
                        template: '<div class="sw-button-process"><slot /></div>',
                        props: ['disabled'],
                    },
                    'sw-card': {
                        template: '<div class="sw-card"><slot /></div>',
                    },
                    'sw-switch-field': true,
                    'sw-number-field': true,
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
                    'sw-alert': true,
                    'sw-skeleton': true,
                    'sw-language-switch': true,
                    'sw-custom-field-set-renderer': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-number-range/page/sw-settings-number-range-create', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

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
});
