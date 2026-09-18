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
                    'sw-radio-field': await wrapTestComponent('sw-radio-field'),
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

    describe('tax display and price basis', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper(['customer_groups.editor']);
            await flushPromises();
        });

        it('should render both radio groups with a description per option', async () => {
            const taxDisplay = wrapper.find('.sw-settings-customer-group-detail__tax-display');
            const priceBasis = wrapper.find('.sw-settings-customer-group-detail__price-basis');

            expect(taxDisplay.find('.sw-field__label label').text()).toBe(
                'sw-settings-customer-group.detail.taxDisplay.label',
            );
            expect(taxDisplay.findAll('.sw-field__radio-option-label span').map((option) => option.text())).toEqual([
                'sw-settings-customer-group.detail.taxDisplay.grossLabel',
                'sw-settings-customer-group.detail.taxDisplay.netLabel',
            ]);
            expect(
                taxDisplay.findAll('.sw-field__radio-option-description').map((description) => description.text()),
            ).toEqual([
                'sw-settings-customer-group.detail.taxDisplay.grossDescription',
                'sw-settings-customer-group.detail.taxDisplay.netDescription',
            ]);

            expect(priceBasis.find('.sw-field__label label').text()).toBe(
                'sw-settings-customer-group.detail.priceBasis.label',
            );
            expect(priceBasis.findAll('.sw-field__radio-option-label span').map((option) => option.text())).toEqual([
                'sw-settings-customer-group.detail.priceBasis.grossLabel',
                'sw-settings-customer-group.detail.priceBasis.netLabel',
            ]);
            expect(
                priceBasis.findAll('.sw-field__radio-option-description').map((description) => description.text()),
            ).toEqual([
                'sw-settings-customer-group.detail.priceBasis.grossDescription',
                'sw-settings-customer-group.detail.priceBasis.netDescription',
            ]);
        });

        it('should render a section title above the signup form switch', async () => {
            expect(wrapper.find('.sw-settings-customer-group-detail__registration-form-title').text()).toBe(
                'sw-settings-customer-group.detail.registrationFormTitle',
            );
        });

        it('should leave both fields untouched as long as nobody selects anything', async () => {
            expect(wrapper.vm.customerGroup.displayGross).toBe(false);
            expect(wrapper.vm.customerGroup.priceBasis).toBeNull();
        });

        it.each([
            [
                false,
                null,
                'net',
            ],
            [
                true,
                null,
                'gross',
            ],
            [
                false,
                'gross',
                'gross',
            ],
            [
                true,
                'net',
                'net',
            ],
        ])(
            'should show the effective basis for displayGross %s and stored basis %s',
            async (displayGross, storedBasis, effectiveBasis) => {
                wrapper.vm.customerGroup.displayGross = displayGross;
                wrapper.vm.customerGroup.priceBasis = storedBasis;
                await flushPromises();

                expect(wrapper.vm.priceBasis).toBe(effectiveBasis);
                expect(
                    wrapper
                        .find('.sw-settings-customer-group-detail__price-basis')
                        .find('.sw-field__radio-option-checked .sw-field__radio-option-label span')
                        .text(),
                ).toBe(`sw-settings-customer-group.detail.priceBasis.${effectiveBasis}Label`);
            },
        );

        it.each([
            [
                false,
                0,
                true,
                'net',
            ],
            [
                true,
                1,
                false,
                'gross',
            ],
        ])(
            'should materialise the effective basis when the tax display of displayGross %s changes',
            async (displayGross, optionIndex, expectedDisplayGross, expectedBasis) => {
                wrapper.vm.customerGroup.displayGross = displayGross;
                wrapper.vm.customerGroup.priceBasis = null;
                await flushPromises();

                await wrapper.findAll('input[name="sw-field--customerGroup-displayGross"]').at(optionIndex).setValue();
                await flushPromises();

                expect(wrapper.vm.customerGroup.displayGross).toBe(expectedDisplayGross);
                expect(wrapper.vm.customerGroup.priceBasis).toBe(expectedBasis);
            },
        );

        it('should write the basis without touching the tax display when the basis changes', async () => {
            const grossPriceBasis = wrapper.findAll('input[name="sw-field--customerGroup-priceBasis"]').at(0);
            await grossPriceBasis.setValue();
            await flushPromises();

            expect(wrapper.vm.customerGroup.priceBasis).toBe('gross');
            expect(wrapper.vm.customerGroup.displayGross).toBe(false);
        });

        it('should only be editable with edit permission', async () => {
            expect(wrapper.find('.sw-settings-customer-group-detail__tax-display').classes()).not.toContain('is--disabled');
            expect(wrapper.find('.sw-settings-customer-group-detail__price-basis').classes()).not.toContain('is--disabled');

            wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.find('.sw-settings-customer-group-detail__tax-display').classes()).toContain('is--disabled');
            expect(wrapper.find('.sw-settings-customer-group-detail__price-basis').classes()).toContain('is--disabled');
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
