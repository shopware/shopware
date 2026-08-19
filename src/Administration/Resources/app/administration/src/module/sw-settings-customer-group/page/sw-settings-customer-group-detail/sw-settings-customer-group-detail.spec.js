/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

const customerGroupRepository = {
    create: () => {
        return {
            id: '',
            name: '',
            displayGross: false,
            priceBasis: null,
            isNew: () => true,
        };
    },

    get: () => {
        return Promise.resolve({
            id: '1',
            name: 'Net price customer group',
            displayGross: false,
            priceBasis: null,
            registrationActive: true,
            registrationTitle: 'Foobar',
            registrationSalesChannels: new EntityCollection(
                '/customer-group/1/registration-sales-channels',
                'sales_channel',
                Context.api,
                null,
                [
                    {
                        id: '123',
                    },
                ],
            ),
            isNew: () => false,
        });
    },

    search: () => {
        return Promise.resolve([
            {
                id: '123',
                seoPathInfo: 'Hello-world',
                salesChannel: {
                    translated: {
                        name: 'Storefront',
                    },
                    domains: [
                        {
                            languageId: '1234',
                            url: 'http://shopware.test',
                        },
                    ],
                },
                languageId: '1234',
            },
        ]);
    },

    save: jest.fn(() => Promise.resolve({})),
};

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-customer-group-detail', {
            sync: true,
        }),
        {
            props: {
                customerGroupId: '1',
            },
            global: {
                mocks: {
                    $route: { query: '' },
                },

                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`,
                    },
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'mt-card': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-settings-customer-group-price-preview': await wrapTestComponent(
                        'sw-settings-customer-group-price-preview',
                    ),
                    'sw-text-field': {
                        props: [
                            'label',
                            'value',
                            'disabled',
                            'copyable',
                        ],
                        template: `
                        <div class="sw-text-field-stub"
                             :label="label"
                            :value="value"
                            :disabled="disabled"
                            :copyable="copyable"
                        >
                          <slot></slot>
                        </div>`,
                    },
                    'mt-textarea': true,
                    'sw-text-editor': true,
                    'sw-language-info': true,
                    'sw-button-process': true,

                    'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-label': true,
                    'sw-loader': true,
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-highlight-text': true,
                    'sw-popover': {
                        props: ['popoverClass'],
                        template: `
                    <div class="sw-popover" :class="popoverClass">
                        <slot></slot>
                    </div>`,
                    },
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-custom-field-set-renderer': true,
                    'sw-skeleton': true,
                    'sw-language-switch': true,
                    'sw-product-variant-info': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-field-error': true,
                },

                provide: {
                    repositoryFactory: {
                        create: (name) => {
                            switch (name) {
                                case 'customer_group':
                                    return customerGroupRepository;
                                default:
                                    throw new Error(`No repository for ${name} configured`);
                            }
                        },
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                },
            },
        },
    );
}

describe('src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail', () => {
    describe('should not able to save without edit permission', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper();
            await wrapper.vm.$nextTick();
        });

        [
            {
                name: 'save button',
                selector: '.sw-settings-customer-group-detail__save',
            },
            {
                name: 'name field ',
                selector: '.sw-settings-customer-group-detail__name',
            },
            {
                name: 'registration form switch',
                selector: '.sw-settings-customer-group-detail__registration-form-switch',
            },
            {
                name: 'form title field',
                selector: '.mt-text-field',
            },
            { name: 'form editor', selector: 'sw-text-editor-stub' },
            {
                name: 'only company switch',
                selector: '.sw-settings-customer-group-detail__registration-only-companies-can-register',
            },
            {
                name: 'seo meta field',
                selector: 'mt-textarea-stub[label="sw-settings-customer-group.registration.seoMetaDescription"]',
            },
            {
                name: 'sales channel multiple select',
                selector: '.sw-entity-multi-select',
            },
        ].forEach(({ name, selector }) => {
            it(`${name} should be disabled`, async () => {
                await flushPromises();
                const element = wrapper.findComponent(selector);

                // Condition for different types of components
                if (element.attributes().hasOwnProperty('disabled')) {
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(element.attributes().disabled).toBeTruthy();
                } else {
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(element.props().disabled).toBeTruthy();
                }
            });
        });

        it('should show warning tooltip', async () => {
            expect(wrapper.vm.tooltipSave).toStrictEqual({
                message: 'sw-privileges.tooltip.warning',
                disabled: false,
                showOnDisabledElements: true,
            });
        });
    });

    describe('should able to save with edit permission', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper(['customer_groups.editor']);
            await wrapper.vm.$nextTick();
        });

        [
            {
                name: 'save button',
                selector: '.sw-settings-customer-group-detail__save',
            },
            {
                name: 'name field ',
                selector: '.sw-settings-customer-group-detail__name',
            },
            {
                name: 'registration form switch',
                selector: '.sw-settings-customer-group-detail__registration-form-switch',
            },
            {
                name: 'form title field',
                selector: '.mt-text-field',
            },
            { name: 'form editor', selector: 'sw-text-editor-stub' },
            {
                name: 'only company switch',
                selector: '.sw-settings-customer-group-detail__registration-only-companies-can-register',
            },
            {
                name: 'seo meta field',
                selector: 'mt-textarea-stub[label="sw-settings-customer-group.registration.seoMetaDescription"]',
            },
            {
                name: 'sales channel multiple select',
                selector: '.sw-entity-multi-select',
            },
        ].forEach(({ name, selector }) => {
            it(`${name} should be enabled`, async () => {
                const element = wrapper.find(selector);
                // disabled attribute can be undefined, false, or the string "false"
                const disabled = element.attributes().disabled;
                expect(disabled === undefined || disabled === false || disabled === 'false').toBe(true);
            });
        });

        it('should show save shortcut tooltip', async () => {
            expect(wrapper.vm.tooltipSave).toStrictEqual({
                message: 'CTRL + S',
                appearance: 'light',
            });
        });
    });

    describe('price display', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper(['customer_groups.editor']);
            await flushPromises();
        });

        it('should offer the three price display modes with a description each', async () => {
            const priceDisplayMode = wrapper.find('.sw-settings-customer-group-detail__price-display-mode');

            expect(priceDisplayMode.exists()).toBe(true);
            expect(wrapper.vm.priceDisplayModeOptions).toEqual([
                {
                    value: 'gross',
                    label: 'sw-settings-customer-group.priceDisplay.modeGrossLabel',
                    description: 'sw-settings-customer-group.priceDisplay.modeGrossDescription',
                },
                {
                    value: 'net',
                    label: 'sw-settings-customer-group.priceDisplay.modeNetLabel',
                    description: 'sw-settings-customer-group.priceDisplay.modeNetDescription',
                },
                {
                    value: 'grossNetBase',
                    label: 'sw-settings-customer-group.priceDisplay.modeGrossNetBaseLabel',
                    description: 'sw-settings-customer-group.priceDisplay.modeGrossNetBaseDescription',
                },
            ]);

            await priceDisplayMode.find('.sw-select__selection').trigger('click');
            await flushPromises();

            expect(wrapper.findAll('.sw-select-result')).toHaveLength(3);
            expect(wrapper.find('.sw-select-option--gross').exists()).toBe(true);
            expect(wrapper.find('.sw-select-option--net').exists()).toBe(true);
            expect(wrapper.find('.sw-select-option--grossNetBase').exists()).toBe(true);
            expect(wrapper.find('.sw-select-option--grossNetBase .sw-select-result__result-item-description').text()).toBe(
                'sw-settings-customer-group.priceDisplay.modeGrossNetBaseDescription',
            );
        });

        it.each([
            [
                false,
                null,
                'net',
            ],
            [
                false,
                'net',
                'net',
            ],
            [
                false,
                'gross',
                'net',
            ],
            [
                true,
                null,
                'gross',
            ],
            [
                true,
                'gross',
                'gross',
            ],
            [
                true,
                'net',
                'grossNetBase',
            ],
        ])('should derive the mode from displayGross %s and price basis %s', async (displayGross, priceBasis, mode) => {
            wrapper.vm.customerGroup.displayGross = displayGross;
            wrapper.vm.customerGroup.priceBasis = priceBasis;
            await flushPromises();

            expect(wrapper.vm.priceDisplayMode).toBe(mode);
            expect(wrapper.find('.sw-single-select__selection-text').text()).toBe(
                wrapper.vm.priceDisplayModeOptions.find((option) => option.value === mode).label,
            );
        });

        /**
         * @deprecated tag:v6.8.0 - The price basis stays empty as long as the display mode decides the
         * calculation basis, the test goes away with that coupling.
         */
        it.deprecated('v6.8.0.0').each([
            [
                'gross',
                true,
                null,
            ],
            [
                'net',
                false,
                null,
            ],
            [
                'grossNetBase',
                true,
                'net',
            ],
        ])('should write %s without an explicit price basis', async (mode, displayGross, priceBasis) => {
            wrapper.vm.priceDisplayMode = mode;
            await flushPromises();

            expect(wrapper.vm.customerGroup.displayGross).toBe(displayGross);
            expect(wrapper.vm.customerGroup.priceBasis).toBe(priceBasis);
        });

        it.activeFeatureFlags(['v6.8.0.0']).each([
            [
                'gross',
                true,
                'gross',
            ],
            [
                'net',
                false,
                'net',
            ],
            [
                'grossNetBase',
                true,
                'net',
            ],
        ])('should write %s with an explicit price basis', async (mode, displayGross, priceBasis) => {
            wrapper.vm.priceDisplayMode = mode;
            await flushPromises();

            expect(wrapper.vm.customerGroup.displayGross).toBe(displayGross);
            expect(wrapper.vm.customerGroup.priceBasis).toBe(priceBasis);
        });

        it('should write both fields when a mode is selected in the select', async () => {
            const priceDisplayMode = wrapper.find('.sw-settings-customer-group-detail__price-display-mode');
            await priceDisplayMode.find('.sw-select__selection').trigger('click');
            await flushPromises();

            await wrapper.find('.sw-select-option--grossNetBase').trigger('click');
            await flushPromises();

            expect(wrapper.vm.customerGroup.displayGross).toBe(true);
            expect(wrapper.vm.customerGroup.priceBasis).toBe('net');
        });

        it('should update the preview when the mode changes', async () => {
            const preview = wrapper.find('.sw-settings-customer-group-detail__price-preview');

            expect(preview.exists()).toBe(true);
            expect(preview.find('.sw-settings-customer-group-price-preview__row--merchant').classes()).toContain(
                'is--fixed',
            );

            wrapper.vm.priceDisplayMode = 'gross';
            await flushPromises();

            expect(preview.find('.sw-settings-customer-group-price-preview__row--total').classes()).toContain('is--fixed');
            expect(preview.find('.sw-settings-customer-group-price-preview__row--merchant').classes()).toContain(
                'is--varying',
            );
        });

        it('should only be editable with edit permission', async () => {
            expect(wrapper.find('.sw-settings-customer-group-detail__price-display-mode').classes()).not.toContain(
                'is--disabled',
            );

            wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.find('.sw-settings-customer-group-detail__price-display-mode').classes()).toContain(
                'is--disabled',
            );
        });
    });

    describe('should persist customer group', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper();
            await wrapper.vm.$nextTick();
        });

        it('should reload customer group on saved changes', async () => {
            const onLoadCustomerGroupSpy = jest.spyOn(wrapper.vm, 'loadCustomerGroup');
            const element = wrapper.find('.sw-settings-customer-group-detail__save');
            await element.trigger('click');

            expect(wrapper.vm.customerGroupRepository.save).toHaveBeenCalledTimes(1);
            expect(onLoadCustomerGroupSpy).toHaveBeenCalled();
        });
    });
});
