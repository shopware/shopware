/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

const expandedGroup = { id: 'group-id', expressionForListings: true, representation: 'box' };

async function createWrapper(variantListingConfig) {
    const product = reactive({ id: 'product-id', variantListingConfig });

    const wrapper = mount(await wrapTestComponent('sw-product-variants-delivery-listing', { sync: true }), {
        props: {
            product,
            selectedGroups: [{ id: 'group-id', translated: { name: 'Size' } }],
        },
        global: {
            stubs: {
                'sw-radio-field': {
                    name: 'sw-radio-field',
                    props: ['value', 'options', 'disabled'],
                    emits: ['update:value'],
                    template: '<div class="sw-radio-field"></div>',
                },
                'mt-checkbox': {
                    name: 'mt-checkbox',
                    props: ['checked', 'disabled', 'label'],
                    emits: ['update:checked'],
                    template: '<div class="mt-checkbox"></div>',
                },
                'sw-entity-single-select': true,
                'sw-product-variant-info': true,
                'sw-select-result': true,
            },
        },
    });

    return { wrapper, product };
}

function getRadioFields(wrapper) {
    const [listingModeField, variantModeField] = wrapper.findAllComponents({ name: 'sw-radio-field' });

    return { listingModeField, variantModeField };
}

describe('src/module/sw-product/component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-listing', () => {
    it.each([
        [
            'nothing is saved',
            { displayParent: null, configuratorGroupConfig: [] },
            'single',
            false,
        ],
        [
            'nothing is saved for a product created in the administration',
            { displayParent: null, mainVariantId: null, configuratorGroupConfig: null },
            'single',
            false,
        ],
        [
            'the main product is shown',
            { displayParent: true, mainVariantId: null, configuratorGroupConfig: null },
            'single',
            true,
        ],
        [
            'a main variant is chosen',
            { displayParent: false, mainVariantId: 'variant-id', configuratorGroupConfig: [] },
            'single',
            false,
        ],
        [
            'a variant is shown without a chosen main variant',
            { displayParent: false, mainVariantId: null, configuratorGroupConfig: null },
            'single',
            false,
        ],
        [
            'no property is enabled for listings',
            {
                displayParent: null,
                mainVariantId: null,
                configuratorGroupConfig: [{ ...expandedGroup, expressionForListings: false }],
            },
            'single',
            false,
        ],
        [
            'a property is enabled for listings',
            { displayParent: null, mainVariantId: null, configuratorGroupConfig: [expandedGroup] },
            'expanded',
            false,
        ],
        [
            'the main product is shown although a property is enabled for listings',
            { displayParent: true, mainVariantId: null, configuratorGroupConfig: [expandedGroup] },
            'single',
            true,
        ],
    ])(
        'should show the storefront presentation when %s',
        async (_, variantListingConfig, expectedListingMode, expectedMainProduct) => {
            const { wrapper, product } = await createWrapper(variantListingConfig);
            const { listingModeField, variantModeField } = getRadioFields(wrapper);

            expect(product.listingMode).toBe(expectedListingMode);
            expect(listingModeField.props('value')).toBe(expectedListingMode);
            expect(variantModeField.props('value')).toBe(expectedMainProduct);
        },
    );

    it('should not change the saved configuration when the tab is opened', async () => {
        const { product } = await createWrapper({
            displayParent: null,
            mainVariantId: null,
            configuratorGroupConfig: [expandedGroup],
        });

        expect(product.variantListingConfig).toEqual({
            displayParent: null,
            mainVariantId: null,
            configuratorGroupConfig: [expandedGroup],
        });
    });

    it('should keep the variant choice when the listing mode is switched', async () => {
        const { wrapper, product } = await createWrapper({
            displayParent: false,
            mainVariantId: null,
            configuratorGroupConfig: [],
        });
        const { listingModeField, variantModeField } = getRadioFields(wrapper);

        await listingModeField.vm.$emit('update:value', 'expanded');
        await listingModeField.vm.$emit('update:value', 'single');

        expect(product.listingMode).toBe('single');
        expect(product.variantListingConfig.displayParent).toBe(false);
        expect(variantModeField.props('value')).toBe(false);
    });

    it('should enable a property for listings', async () => {
        const { wrapper, product } = await createWrapper({
            displayParent: null,
            mainVariantId: null,
            configuratorGroupConfig: [],
        });

        await getRadioFields(wrapper).listingModeField.vm.$emit('update:value', 'expanded');
        await wrapper.findComponent({ name: 'mt-checkbox' }).vm.$emit('update:checked', true);

        expect(product.listingMode).toBe('expanded');
        expect(product.variantListingConfig.configuratorGroupConfig).toEqual([expandedGroup]);
    });
});
