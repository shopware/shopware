/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import { deprecations } from 'test/_helper_/deprecations';

const PRODUCT_COMPARISON_TYPE_ID = Shopware.Defaults.productComparisonTypeId;
const AGENTIC_COMMERCE_TYPE_ID = Shopware.Defaults.agenticCommerceTypeId;

const mockSalesChannelTypes = [
    {
        id: PRODUCT_COMPARISON_TYPE_ID,
        iconName: 'comparison-icon',
        translated: { name: 'Product Comparison', description: 'A comparison feed' },
    },
    {
        id: AGENTIC_COMMERCE_TYPE_ID,
        iconName: 'agentic-icon',
        translated: { name: 'Agentic Commerce', description: 'Agentic Commerce' },
    },
];

function createAddChannelAction({ disabled = () => false, loading = () => false } = {}) {
    return { disabled, loading };
}

async function createWrapper({ addChannelAction = createAddChannelAction(), productStreamsExist = true } = {}) {
    const mockSearch = jest
        .fn()
        .mockResolvedValue(Object.assign([...mockSalesChannelTypes], { total: mockSalesChannelTypes.length }));

    return mount(await wrapTestComponent('sw-sales-channel-modal-grid', { sync: true }), {
        props: {
            addChannelAction,
            productStreamsExist,
        },
        global: {
            stubs: {
                'sw-grid': {
                    template: '<div class="sw-grid"><slot name="columns" v-for="item in items" :item="item" /></div>',
                    props: [
                        'items',
                        'selectable',
                        'header',
                        'table',
                    ],
                },
                'sw-grid-column': {
                    template: '<div class="sw-grid-column"><slot /></div>',
                    props: [
                        'flex',
                        'align',
                        'label',
                        'dataIndex',
                    ],
                },
                'mt-button': {
                    template: '<button :disabled="disabled || undefined" @click="$emit(\'click\')"><slot /></button>',
                    props: [
                        'disabled',
                        'isLoading',
                        'size',
                        'variant',
                    ],
                    emits: ['click'],
                },
                'mt-icon': {
                    template: '<span class="mt-icon" />',
                    props: ['name'],
                },
                'mt-promo-badge': {
                    template: '<span class="mt-promo-badge" />',
                    props: ['variant', 'size'],
                },
                'sw-loader': true,
                'sw-extension-teaser-sales-channel': true,
            },
            directives: {
                tooltip: {},
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: mockSearch,
                    }),
                },
            },
        },
    });
}

describe('sw-sales-channel-modal-grid', () => {
    it('renders a button for each sales channel type after loading', async () => {
        const wrapper = await createWrapper();

        const buttons = wrapper.findAll('.sw-sales-channel-modal__add-channel-action');
        expect(buttons).toHaveLength(mockSalesChannelTypes.length);
    });

    it('emits grid-channel-add with the type id when add button is clicked', async () => {
        const wrapper = await createWrapper();

        const buttons = wrapper.findAll('.sw-sales-channel-modal__add-channel-action');
        await buttons[0].trigger('click');

        expect(wrapper.emitted('grid-channel-add')).toBeTruthy();
        expect(wrapper.emitted('grid-channel-add')[0][0]).toBe(PRODUCT_COMPARISON_TYPE_ID);
    });

    it('emits grid-detail-open with the type when description is clicked', async () => {
        const wrapper = await createWrapper();

        const buttons = wrapper.findAll('.sw-sales-channel-modal-grid__item-description');
        await buttons[0].trigger('click');

        expect(wrapper.emitted('grid-detail-open')).toBeTruthy();
        expect(wrapper.emitted('grid-detail-open')[0][0]).toEqual(mockSalesChannelTypes[0]);
    });

    it('disables the add button when addChannelAction.disabled returns true', async () => {
        const wrapper = await createWrapper({
            addChannelAction: createAddChannelAction({ disabled: (id) => id === AGENTIC_COMMERCE_TYPE_ID }),
        });

        const buttons = wrapper.findAll('.sw-sales-channel-modal__add-channel-action');

        expect(buttons[0].attributes('disabled')).toBeUndefined();
        expect(buttons[1].attributes('disabled')).toBeDefined();
    });

    // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
    it.deprecated('v6.8.0.0')('disables the add button for agentic commerce type', async () => {
        const wrapper = await createWrapper();

        const buttons = wrapper.findAll('.sw-sales-channel-modal__add-channel-action');
        const agenticIndex = mockSalesChannelTypes.findIndex((t) => t.id === AGENTIC_COMMERCE_TYPE_ID);
        expect(buttons[agenticIndex].attributes('disabled')).toBeDefined();
    });

    describe('getTooltip', () => {
        // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
        it.deprecated('v6.8.0.0')('uses the agentic commerce message key for the agentic commerce type', async () => {
            const wrapper = await createWrapper();

            const agenticItem = mockSalesChannelTypes.find((t) => t.id === AGENTIC_COMMERCE_TYPE_ID);
            const tooltip = wrapper.vm.getTooltip(agenticItem);

            expect(tooltip.message).toBe(wrapper.vm.$t('sw-sales-channel.modal.messageAgenticCommerce'));
        });

        it('uses the no-product-streams message key for the product comparison type', async () => {
            const wrapper = await createWrapper({
                addChannelAction: createAddChannelAction({ disabled: (id) => id === PRODUCT_COMPARISON_TYPE_ID }),
            });

            const comparisonItem = mockSalesChannelTypes.find((t) => t.id === PRODUCT_COMPARISON_TYPE_ID);
            const tooltip = wrapper.vm.getTooltip(comparisonItem);

            expect(tooltip.message).toBe(wrapper.vm.$t('sw-sales-channel.modal.messageNoProductStreams'));
        });
    });

    describe('isDisabled', () => {
        it('returns false for a regular type when addChannelAction.disabled is false', async () => {
            const wrapper = await createWrapper();

            const regularItem = mockSalesChannelTypes[0];
            expect(wrapper.vm.isDisabled(regularItem)).toBe(false);
        });

        it('returns true when addChannelAction.disabled returns true', async () => {
            const wrapper = await createWrapper({
                addChannelAction: createAddChannelAction({ disabled: () => true }),
            });

            const regularItem = mockSalesChannelTypes[0];
            expect(wrapper.vm.isDisabled(regularItem)).toBe(true);
        });

        // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
        it.deprecated('v6.8.0.0')('returns true for agentic commerce type regardless of addChannelAction', async () => {
            const wrapper = await createWrapper({
                addChannelAction: createAddChannelAction({ disabled: () => false }),
            });

            const agenticItem = mockSalesChannelTypes.find((t) => t.id === AGENTIC_COMMERCE_TYPE_ID);
            expect(wrapper.vm.isDisabled(agenticItem)).toBe(true);
        });
    });

    // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
    it.deprecated('v6.8.0.0').each([
        ['isAgenticCommerceSalesChannelType()', (vm) => vm.isAgenticCommerceSalesChannelType(AGENTIC_COMMERCE_TYPE_ID)],
        ['showAgenticCommerceType()', (vm) => vm.showAgenticCommerceType()],
    ])('should report the use of the deprecated member %s', async (member, use) => {
        const wrapper = await createWrapper();

        // eslint-disable-next-line sw-test-rules/no-vm-access -- extensions reach the deprecated member on the instance
        await use(wrapper.vm);

        expect(deprecations).toHaveTriggered(`sw-sales-channel-modal-grid.${member} is deprecated.`);
    });

    describe('core use of the deprecated Agentic Commerce members', () => {
        afterEach(() => {
            Shopware.Component.getOverrideRegistry().delete('sw-sales-channel-modal-grid');
        });

        // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
        it.deprecated('v6.8.0.0')('applies an override of showAgenticCommerceType() without reporting it', async () => {
            Shopware.Component.override('sw-sales-channel-modal-grid', {
                methods: {
                    showAgenticCommerceType() {
                        return true;
                    },
                },
            });

            const wrapper = await createWrapper();
            await flushPromises();

            const buttons = wrapper.findAll('.sw-sales-channel-modal__add-channel-action');
            const agenticIndex = mockSalesChannelTypes.findIndex((type) => type.id === AGENTIC_COMMERCE_TYPE_ID);
            expect(buttons[agenticIndex].attributes('disabled')).toBeUndefined();
            expect(deprecations).not.toHaveTriggered('sw-sales-channel-modal-grid.showAgenticCommerceType() is deprecated');
        });

        it.activeFeatureFlags(['v6.8.0.0'])(
            'treats the agentic commerce type as if the deprecated members were removed',
            async () => {
                const wrapper = await createWrapper();
                await flushPromises();

                const buttons = wrapper.findAll('.sw-sales-channel-modal__add-channel-action');
                const agenticIndex = mockSalesChannelTypes.findIndex((type) => type.id === AGENTIC_COMMERCE_TYPE_ID);
                expect(buttons[agenticIndex].attributes('disabled')).toBeUndefined();
            },
        );
    });
});
