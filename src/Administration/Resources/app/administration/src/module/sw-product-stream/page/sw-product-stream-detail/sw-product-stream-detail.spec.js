/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import { MtSwitch } from '@shopware-ag/meteor-component-library';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/custom-field-set',
    status: 200,
    response: {
        data: [
            {
                id: 'custom_field_set_id1',
                type: 'custom_field_set',
                attributes: {
                    name: 'custom_field_set_1',
                    config: { label: { 'en-GB': 'Field Set 1' } },
                    active: true,
                    global: false,
                    position: 1,
                    appId: null,
                },
                relationships: {
                    customFields: {
                        data: [
                            {
                                type: 'custom_field',
                                id: 'custom_field_id1',
                            },
                        ],
                        links: {
                            related: 'http://host/api/custom-field-set/custom_field_set_id1/custom-fields',
                        },
                    },
                },
            },
        ],
        included: [
            {
                id: 'custom_field_id1',
                type: 'custom_field',
                attributes: {
                    name: 'custom_field_1',
                    type: 'int',
                    config: {
                        type: 'number',
                        label: { 'en-GB': 'First custom field number' },
                        numberType: 'int',
                        placeholder: { 'en-GB': 'Type a number...' },
                        componentName: 'sw-field',
                        customFieldType: 'number',
                        customFieldPosition: 1,
                    },
                    active: true,
                    customFieldSetId: 'custom_field_set_id1',
                },
                relationships: {
                    customFieldSet: {},
                },
            },
            {
                id: 'custom_field_set_relation_id1',
                type: 'custom_field_set_relation',
                attributes: {
                    customFieldSetId: 'custom_field_set_id1',
                    entityName: 'customer',
                },
                relationships: {
                    customFieldSet: {},
                },
            },
        ],
    },
});

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-stream-detail', { sync: true }), {
        props: {
            productStreamId: null,
        },
        global: {
            stubs: {
                'sw-page': {
                    template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`,
                },
                'sw-button-group': true,
                'sw-button-process': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-card-view': {
                    template: '<div><slot></slot></div>',
                },
                'sw-skeleton': true,
                'sw-language-info': true,
                'sw-text-field': true,
                'sw-textarea-field': true,
                'sw-condition-tree': true,
                'sw-language-switch': true,
                'sw-product-stream-modal-preview': true,
                'sw-custom-field-set-renderer': true,
                'mt-banner': true,
                'mt-switch': MtSwitch,
            },
            provide: {
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve({}),
                },
                productStreamConditionService: {},
                productTypeService: {
                    fetchProductTypes: () =>
                        Promise.resolve([
                            'digital',
                            'physical',
                        ]),
                },
            },
        },
    });
}

describe('src/module/sw-product-stream/page/sw-product-stream-detail', () => {
    it('should fetch custom product custom fields and add them to the condition select list', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const relatedCustomFields = wrapper.vm.productCustomFields;
        expect(relatedCustomFields).toHaveProperty('custom_field_1');
    });

    it('should show warning banner when indexing is disabled', async () => {
        Shopware.Context.app.productStreamIndexingEnabled = false;

        const wrapper = await createWrapper();
        await flushPromises();

        const banner = wrapper.find('.sw-product-stream-detail__product-stream-warning mt-banner-stub');
        expect(banner.exists()).toBe(true);
    });

    it('should not show warning banner when indexing is enabled', async () => {
        Shopware.Context.app.productStreamIndexingEnabled = true;

        const wrapper = await createWrapper();
        await flushPromises();

        const banner = wrapper.find('.sw-product-stream-detail__product-stream-warning mt-banner-stub');
        expect(banner.exists()).toBe(false);
    });

    it('should show warning when filters contain product states field', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.productStream = {
            id: 'stream-1',
            filters: {
                entity: 'product_stream',
            },
        };
        wrapper.vm.productStreamFiltersTree = [
            {
                field: 'states',
            },
        ];

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showProductStatesFilterWarning).toBe(true);
    });

    it('should not show warning when filters do not contain product states', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.productStream = {
            id: 'stream-1',
            filters: {
                entity: 'product_stream',
            },
        };
        wrapper.vm.productStreamFilters = [
            {
                field: 'stock',
            },
        ];

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showProductStatesFilterWarning).toBe(false);
    });

    it('should render and update the display-as-group toggle', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.productStream = {
            id: 'stream-1',
            displayAsGroup: true,
            filters: {
                entity: 'product_stream',
            },
        };

        await wrapper.vm.$nextTick();

        const field = wrapper.getComponent(MtSwitch);

        expect(field.props('modelValue')).toBe(true);
        expect(field.props('label')).toBe('sw-product-stream.detail.labelDisplayAsGroup');
        expect(field.get('input').element.checked).toBe(true);

        field.vm.$emit('update:modelValue', false);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productStream.displayAsGroup).toBe(false);
    });

    it('should keep displayAsGroup when loading an existing product stream', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        const productStreamRepository = wrapper.vm.productStreamRepository;

        wrapper.vm.loadFilters = jest.fn().mockResolvedValue();
        productStreamRepository.get = jest.fn().mockResolvedValue({
            id: 'stream-1',
            displayAsGroup: false,
            filters: {
                entity: 'product_stream',
            },
        });

        await wrapper.vm.loadEntityData('stream-1');

        expect(wrapper.vm.productStream.displayAsGroup).toBe(false);
        expect(productStreamRepository.get).toHaveBeenCalledWith('stream-1', Shopware.Context.api);
    });
});
