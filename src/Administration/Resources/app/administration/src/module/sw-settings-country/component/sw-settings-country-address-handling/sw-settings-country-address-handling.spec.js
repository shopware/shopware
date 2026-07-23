/* eslint-disable sw-test-rules/test-file-max-lines-warning, sw-test-rules/test-file-max-lines-error */

/**
 * @sw-package fundamentals@discovery
 */

import { mount } from '@vue/test-utils';

const addressFormat = [
    [
        'address/company',
        'symbol/dash',
        'address/department',
    ],
    [
        'address/first_name',
        'address/last_name',
    ],
    ['address/street'],
    [
        'address/zipcode',
        'address/city',
    ],
    ['address/country'],
];

let stubs = {};
let renderMock;

async function createWrapper(privileges = [], customPropsData = {}) {
    renderMock = jest.fn(() =>
        Promise.resolve({
            rendered:
                'Christa Stracke<br/> \\n \\n Philip Inlet<br/> \\n \\n \\n \\n 22005-3637 New Marilyneside<br/> \\n \\n Moldova (Republic of)<br/><br/>',
        }),
    );

    stubs = {
        'sw-settings-country-address-handling': await wrapTestComponent('sw-settings-country-address-handling', {
            sync: true,
        }),
        'mt-card': {
            template: '<div class="mt-card"><slot name="headerRight"></slot><slot></slot></div>',
        },
        'sw-container': true,
        'sw-ignore-class': true,
        'sw-text-field': true,
        'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
        'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated'),
        'sw-field-error': true,
        'sw-help-text': true,
        'sw-extension-component-section': true,
        'sw-multi-snippet-drag-and-drop': await wrapTestComponent('sw-multi-snippet-drag-and-drop'),
        'sw-select-base': await wrapTestComponent('sw-select-base'),
        'sw-block-field': await wrapTestComponent('sw-block-field'),
        'sw-base-field': await wrapTestComponent('sw-base-field'),
        'sw-label': await wrapTestComponent('sw-label'),
        'sw-settings-country-preview-template': await wrapTestComponent('sw-settings-country-preview-template'),
        'sw-settings-country-new-snippet-modal': {
            template: `
                    <div class="sw-modal sw-settings-country-new-snippet-modal">
                        <slot name="modal-header" @click.prevent="$emit('modal-close')"></slot>
                        <slot></slot>
                        <slot name="modal-footer"></slot>
                    </div>`,
        },
        'sw-context-menu': await wrapTestComponent('sw-context-menu'),
        'sw-context-button': await wrapTestComponent('sw-context-button'),
        'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
        'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
        'sw-popover': await wrapTestComponent('sw-popover'),
        'sw-popover-deprecated': {
            props: ['popoverClass'],
            template: `
                    <div class="sw-popover" :class="popoverClass">
                        <slot></slot>
                    </div>`,
        },
        'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
        'sw-select-result': await wrapTestComponent('sw-select-result'),
        'sw-highlight-text': true,
        'sw-loader': true,
        'sw-product-variant-info': true,
        'router-link': true,
        'sw-inheritance-switch': true,
        'sw-color-badge': true,
        'sw-ai-copilot-badge': true,
    };

    return mount(
        {
            template: `
<sw-settings-country-address-handling
    :country="country"
    :isLoading="isLoading"
    @update:country="onUpdateCountry"
/>
`,
            props: {
                country: {
                    type: Object,
                    required: true,
                },
                isLoading: {
                    type: Boolean,
                    required: true,
                },
            },
            methods: {
                onUpdateCountry(path, value) {
                    Shopware.Utils.object.set(this.country, path, value);
                },
            },
        },
        {
            global: {
                renderStubDefaultSlot: true,
                directives: {
                    tooltip: {},
                    droppable: {},
                    draggable: {},
                },
                mocks: {
                    $t: (key) => key,
                    $route: {
                        params: {
                            id: 'id',
                        },
                    },
                    $device: {
                        getSystemKey: () => {},
                        onResize: () => {},
                    },
                },

                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve([
                                    {
                                        id: 'id',
                                        defaultBillingAddress: {
                                            firstName: 'Y',
                                            lastName: 'Tran',
                                            company: '',
                                            department: '',
                                            street: 'Ebbinghoff 10',
                                            zipcode: '48624',
                                            city: 'Schöppingen',
                                            country: {
                                                name: 'Germany',
                                            },
                                        },
                                    },
                                ]);
                            },
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customSnippetApiService: {
                        snippets: () => {
                            return Promise.resolve({
                                data: [
                                    'symbol/dash',
                                    'symbol/comma',
                                    'address/country_state',
                                    'address/salutation',
                                ],
                            });
                        },

                        render: renderMock,
                    },
                    countryApiService: {
                        defaultCountryAddressFormat: () =>
                            Promise.resolve({
                                data: addressFormat,
                            }),
                    },
                    userInputSanitizeService: {},
                },

                stubs,
            },

            props: {
                country: {
                    isNew: () => false,
                    addressFormat,
                    ...customPropsData,
                },
                isLoading: false,
            },
        },
    );
}

describe('module/sw-settings-country/component/sw-settings-country-address-handling', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Store.get('session').setCurrentUser({});
    });

    it('should be able to edit the address handling tab', async () => {
        wrapper = await createWrapper(
            [
                'country.editor',
            ],
            {
                defaultPostalCodePattern: '\\d{5}',
            },
        );
        await flushPromises();

        const countryForceStateInRegistrationField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelForceStateInRegistration"]',
        );

        const countryDisplayStateInRegistrationField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelDisplayStateInRegistration"]',
        );

        const countryPostalCodeRequiredField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelPostalCodeRequired"]',
        );

        const countryCheckPostalCodePatternField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelCheckPostalCodePattern"]',
        );

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]',
        );

        expect(countryForceStateInRegistrationField.attributes('disabled')).toBeUndefined();
        expect(countryDisplayStateInRegistrationField.attributes('disabled')).toBeUndefined();
        expect(countryPostalCodeRequiredField.attributes('disabled')).toBeUndefined();
        expect(countryCheckPostalCodePatternField.attributes('disabled')).toBeUndefined();
        expect(countryCheckAdvancedPostalCodePatternField.attributes('disabled')).toBeDefined();
    });

    it('should not able to edit the address handling tab', async () => {
        wrapper = await createWrapper([], {
            checkAdvancedPostalCodePattern: true,
        });

        await flushPromises();

        const countryForceStateInRegistrationField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelForceStateInRegistration"]',
        );

        const countryDisplayStateInRegistrationField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelDisplayStateInRegistration"]',
        );

        const countryPostalCodeRequiredField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelPostalCodeRequired"]',
        );

        const countryCheckPostalCodePatternField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelCheckPostalCodePattern"]',
        );

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]',
        );

        expect(countryForceStateInRegistrationField.attributes('disabled')).toBeDefined();
        expect(countryDisplayStateInRegistrationField.attributes('disabled')).toBeDefined();
        expect(countryPostalCodeRequiredField.attributes('disabled')).toBeDefined();
        expect(countryCheckPostalCodePatternField.attributes('disabled')).toBeDefined();
        expect(countryCheckAdvancedPostalCodePatternField.attributes('disabled')).toBeDefined();
    });

    it('should lock display state setting when state is required', async () => {
        wrapper = await createWrapper(['country.editor'], {
            displayStateInRegistration: false,
            forceStateInRegistration: true,
        });

        await flushPromises();

        const countryDisplayStateInRegistrationField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelDisplayStateInRegistration"]',
        );

        expect(countryDisplayStateInRegistrationField.element.checked).toBe(true);
        expect(countryDisplayStateInRegistrationField.attributes('disabled')).toBeDefined();
    });

    it('should be able to toggle advanced postal code pattern', async () => {
        wrapper = await createWrapper(
            [
                'country.editor',
            ],
            {
                defaultPostalCodePattern: '\\d{5}',
            },
        );

        await wrapper.setProps({
            country: {
                ...wrapper.vm.country,
                checkPostalCodePattern: false,
            },
        });

        await flushPromises();

        expect(wrapper.find('.advanced-postal-code > .mt-switch input').attributes('disabled')).toBeDefined();

        const checkAdvancedPostalCodePatternField = wrapper.findAll(
            '.sw-settings-country-address-handling__option-items',
        )[3];
        await checkAdvancedPostalCodePatternField.find('.mt-switch input').setChecked();

        await flushPromises();

        expect(wrapper.find('.advanced-postal-code > .mt-switch input').attributes('disabled')).toBeUndefined();
    });

    it('should be not able to toggle advanced postal code pattern', async () => {
        wrapper = await createWrapper(
            [
                'country.editor',
            ],
            {
                defaultPostalCodePattern: '\\d{5}',
            },
        );

        await wrapper.setProps({
            country: {
                ...wrapper.vm.country,
                checkAdvancedPostalCodePattern: true,
                checkPostalCodePattern: true,
            },
        });

        await flushPromises();

        expect(wrapper.find('.advanced-postal-code > .mt-switch input').attributes('disabled')).toBeUndefined();

        const checkPostalCodePatternField = wrapper.findAll('.mt-switch')[3];

        await checkPostalCodePatternField.find('.mt-switch input').setChecked(false);

        await flushPromises();

        expect(wrapper.find('.advanced-postal-code > .mt-switch input').attributes('disabled')).toBeDefined();

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            'input[aria-label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]',
        );

        expect(countryCheckAdvancedPostalCodePatternField.attributes('disabled')).toBeDefined();
    });

    it('should revert advanced postal code pattern when toggle on Advanced validation rules', async () => {
        wrapper = await createWrapper(['country.editor'], {
            defaultPostalCodePattern: '\\d{5}',
        });

        await wrapper.setProps({
            country: {
                ...wrapper.vm.country,
                checkPostalCodePattern: true,
                checkAdvancedPostalCodePattern: true,
                advancedPostalCodePattern: '/^\\d{5}(?:[- ]?\\d{4})?$/',
            },
        });

        await flushPromises();

        const checkPostalCodePatternField = wrapper.findAll('.mt-switch')[3];

        await checkPostalCodePatternField.find('input').setChecked(false);

        await flushPromises();

        expect(wrapper.vm.country.checkAdvancedPostalCodePattern).toBe(false);

        await checkPostalCodePatternField.find('input').setChecked();

        await flushPromises();

        const checkAdvancedPostalCodePattern = wrapper.findAll('.mt-switch')[4];

        await checkAdvancedPostalCodePattern.find('input').setChecked();

        await flushPromises();

        expect(wrapper.vm.country.advancedPostalCodePattern).toBe('/^\\d{5}(?:[- ]?\\d{4})?$/');
    });

    it('should disable postal code validation', async () => {
        for (const prop of [
            {
                checkPostalCodePattern: true,
                checkAdvancedPostalCodePattern: true,
            },
            {},
        ]) {
            wrapper = await createWrapper(['country.editor'], prop);
            await flushPromises();

            const countryCheckPostalCodePatternField = wrapper.find(
                'input[aria-label="sw-settings-country.detail.labelCheckPostalCodePattern"]',
            );

            const countryCheckAdvancedPostalCodePatternField = wrapper.find(
                'input[aria-label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]',
            );

            expect(countryCheckPostalCodePatternField.attributes('disabled')).toBeDefined();
            expect(countryCheckAdvancedPostalCodePatternField.attributes('disabled')).toBeUndefined();
        }
    });

    it('should able to show the modal with insert new snippet', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        expect(wrapper.find('.sw-settings-country-new-snippet-modal').exists()).toBeFalsy();

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);
        expect(addressHandlingWrapper.vm.currentPosition).toBeNull();
        expect(addressHandlingWrapper.vm.isOpenModal).toBe(false);

        const swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop')[0];

        // Open the context menu
        const contextButton = swMultiSnippet.find('.sw-context-button__button');
        await contextButton.trigger('click');
        await flushPromises();

        const menuContextButton = swMultiSnippet.findAll('.sw-context-menu-item')[0];

        await menuContextButton.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-settings-country-new-snippet-modal').exists()).toBeTruthy();
        expect(addressHandlingWrapper.vm.currentPosition).toBe(0);
        expect(addressHandlingWrapper.vm.isOpenModal).toBe(true);
    });

    it('should be able to add a new row above than current row', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.setProps({
            country: { addressFormat },
        });

        await flushPromises();

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet).toHaveLength(5);
        expect(swMultiSnippet[0].findAll('.sw-select-selection-list > li')).toHaveLength(4);

        // Open the context menu
        const contextButton = swMultiSnippet[0].find('.sw-context-button__button');
        await contextButton.trigger('click');
        await flushPromises();

        const menuContextButton = swMultiSnippet[0].findAll('.sw-context-menu-item')[1];
        await menuContextButton.trigger('click');

        await flushPromises();

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet).toHaveLength(6);
        expect(swMultiSnippet[0].findAll('.sw-select-selection-list > li')).toHaveLength(1);
    });

    it('should be able to add a new row below than current row', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.setProps({
            country: { addressFormat },
        });

        await flushPromises();

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet).toHaveLength(5);
        expect(swMultiSnippet[0].findAll('.sw-select-selection-list > li')).toHaveLength(4);
        expect(swMultiSnippet[1].findAll('.sw-select-selection-list > li')).toHaveLength(3);

        // Open the context menu
        const contextButton = swMultiSnippet[0].find('.sw-context-button__button');
        await contextButton.trigger('click');
        await flushPromises();

        const menuContextButton = swMultiSnippet[0].findAll('.sw-context-menu-item')[2];

        await menuContextButton.trigger('click');
        await flushPromises();

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet).toHaveLength(6);
        expect(swMultiSnippet[0].findAll('.sw-select-selection-list > li')).toHaveLength(4);
        expect(swMultiSnippet[1].findAll('.sw-select-selection-list > li')).toHaveLength(1);
    });

    it('should be able to move the current row to the top', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.setProps({
            country: { addressFormat },
        });
        await flushPromises();

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(wrapper.vm.country.addressFormat).toEqual([
            [
                'address/company',
                'symbol/dash',
                'address/department',
            ],
            [
                'address/first_name',
                'address/last_name',
            ],
            ['address/street'],
            [
                'address/zipcode',
                'address/city',
            ],
            ['address/country'],
        ]);

        expect(swMultiSnippet[0].findAll('.sw-select-selection-list > li')).toHaveLength(4);
        expect(swMultiSnippet[4].findAll('.sw-select-selection-list > li')).toHaveLength(2);

        // Open the context menu
        const contextButton = swMultiSnippet[4].find('.sw-context-button__button');
        await contextButton.trigger('click');
        await flushPromises();

        const menuContextButton = swMultiSnippet[4].findAll('.sw-context-menu-item')[3];

        await menuContextButton.trigger('click');
        await flushPromises();

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);
        expect(addressHandlingWrapper.vm.addressFormat).toEqual([
            ['address/country'],
            [
                'address/company',
                'symbol/dash',
                'address/department',
            ],
            [
                'address/first_name',
                'address/last_name',
            ],
            ['address/street'],
            [
                'address/zipcode',
                'address/city',
            ],
        ]);

        expect(swMultiSnippet[0].findAll('.sw-select-selection-list > li')).toHaveLength(2);
        expect(swMultiSnippet[1].findAll('.sw-select-selection-list > li')).toHaveLength(4);
        expect(swMultiSnippet[4].findAll('.sw-select-selection-list > li')).toHaveLength(3);
    });

    it('should be able to move the current row to the bottom', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.setProps({
            country: { addressFormat },
        });

        await flushPromises();

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(wrapper.vm.country.addressFormat).toEqual([
            [
                'address/company',
                'symbol/dash',
                'address/department',
            ],
            [
                'address/first_name',
                'address/last_name',
            ],
            ['address/street'],
            [
                'address/zipcode',
                'address/city',
            ],
            ['address/country'],
        ]);

        expect(swMultiSnippet[1].findAll('.sw-select-selection-list > li')).toHaveLength(3);
        expect(swMultiSnippet[4].findAll('.sw-select-selection-list > li')).toHaveLength(2);

        // Open the context menu
        const contextButton = swMultiSnippet[1].find('.sw-context-button__button');
        await contextButton.trigger('click');
        await flushPromises();

        const menuContextButton = swMultiSnippet[1].findAll('.sw-context-menu-item')[4];

        await menuContextButton.trigger('click');
        await flushPromises();

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(wrapper.vm.country.addressFormat).toEqual([
            [
                'address/company',
                'symbol/dash',
                'address/department',
            ],
            ['address/street'],
            [
                'address/zipcode',
                'address/city',
            ],
            ['address/country'],
            [
                'address/first_name',
                'address/last_name',
            ],
        ]);
        expect(swMultiSnippet[1].findAll('.sw-select-selection-list > li')).toHaveLength(2);
        expect(swMultiSnippet[3].findAll('.sw-select-selection-list > li')).toHaveLength(2);
        expect(swMultiSnippet[4].findAll('.sw-select-selection-list > li')).toHaveLength(3);
    });

    it('should be able to delete the current row', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.setProps({
            country: { addressFormat },
        });

        await flushPromises();

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet).toHaveLength(5);

        // Open the context menu
        const contextButton = swMultiSnippet[0].find('.sw-context-button__button');
        await contextButton.trigger('click');
        await flushPromises();

        const menuContextButton = swMultiSnippet[0].findAll('.sw-context-menu-item')[5];

        await menuContextButton.trigger('click');
        await flushPromises();

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet).toHaveLength(4);
    });

    it('should be able to save config when starting drag', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);
        await addressHandlingWrapper.vm.onDragStart({
            data: {
                index: 0,
                snippet: [
                    'address/company',
                    'symbol/dash',
                    'address/department',
                ],
            },
        });

        expect(addressHandlingWrapper.vm.draggedItem).toEqual({
            index: 0,
            snippet: [
                'address/company',
                'symbol/dash',
                'address/department',
            ],
        });
    });

    it('should not be able to save config with an invalid item when ending drag', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);
        expect(addressHandlingWrapper.vm.draggedItem).toBeNull();
        expect(addressHandlingWrapper.vm.droppedItem).toBeNull();

        await addressHandlingWrapper.vm.onDragEnter(null, null);
        await flushPromises();

        expect(addressHandlingWrapper.vm.draggedItem).toBeNull();
        expect(addressHandlingWrapper.vm.droppedItem).toBeNull();

        await addressHandlingWrapper.vm.onDragStart({
            data: {
                index: 0,
                snippet: [
                    'address/company',
                    'symbol/dash',
                    'address/department',
                ],
            },
        });
        await flushPromises();

        expect(addressHandlingWrapper.vm.draggedItem).toEqual({
            index: 0,
            snippet: [
                'address/company',
                'symbol/dash',
                'address/department',
            ],
        });

        await addressHandlingWrapper.vm.onDragEnter(
            {
                index: 0,
                snippet: [
                    'address/company',
                    'symbol/dash',
                    'address/department',
                ],
            },
            null,
        );

        expect(addressHandlingWrapper.vm.droppedItem).toBeNull();

        await addressHandlingWrapper.vm.onDrop();

        expect(addressHandlingWrapper.vm.draggedItem).toBeNull();
        expect(addressHandlingWrapper.vm.droppedItem).toBeNull();
        expect(addressHandlingWrapper.vm.rowDragPreview).toBeNull();
    });

    it('should be able to save config when drag ends', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);
        expect(addressHandlingWrapper.vm.draggedItem).toBeNull();
        expect(addressHandlingWrapper.vm.droppedItem).toBeNull();

        await addressHandlingWrapper.vm.onDragStart({
            data: {
                index: 0,
                snippet: [
                    'address/company',
                    'symbol/dash',
                    'address/department',
                ],
            },
        });

        expect(addressHandlingWrapper.vm.draggedItem).toEqual({
            index: 0,
            snippet: [
                'address/company',
                'symbol/dash',
                'address/department',
            ],
        });

        await addressHandlingWrapper.vm.onDragEnter(
            {
                index: 0,
                snippet: [
                    'address/company',
                    'symbol/dash',
                    'address/department',
                ],
            },
            {
                index: 1,
                snippet: [
                    'address/company',
                    'symbol/dash',
                    'address/department',
                ],
            },
        );

        expect(addressHandlingWrapper.vm.droppedItem).toEqual({
            index: 1,
            snippet: [
                'address/company',
                'symbol/dash',
                'address/department',
            ],
        });
    });

    it('should be able to move a row on dragging', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/company',
            'symbol/dash',
            'address/department',
        ]);
        expect(wrapper.vm.country.addressFormat[1]).toEqual([
            'address/first_name',
            'address/last_name',
        ]);

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);

        const dragRow = async (dragIndex, dropIndex) => {
            const dragSnippet = wrapper.vm.country.addressFormat[dragIndex];
            const dropSnippet = wrapper.vm.country.addressFormat[dropIndex];

            await addressHandlingWrapper.vm.onDragStart({
                data: {
                    index: dragIndex,
                    snippet: dragSnippet,
                },
            });
            await addressHandlingWrapper.vm.onDragEnter(
                {
                    index: dragIndex,
                    snippet: dragSnippet,
                },
                {
                    index: dropIndex,
                    snippet: dropSnippet,
                },
            );
            await flushPromises();
        };

        await dragRow(0, 3);

        const rowsWhileDragging = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(rowsWhileDragging).toHaveLength(6);
        expect(rowsWhileDragging[0].classes()).toContain('is--row-drag-preview-source');
        expect(rowsWhileDragging[4].classes()).toContain('is--row-placeholder');

        await addressHandlingWrapper.vm.onDragEnter(
            {
                index: 0,
                snippet: wrapper.vm.country.addressFormat[0],
            },
            {
                index: 1,
                snippet: wrapper.vm.country.addressFormat[1],
            },
        );
        await flushPromises();

        const rowsAfterReversingDrag = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(rowsAfterReversingDrag).toHaveLength(6);
        expect(rowsAfterReversingDrag[0].classes()).toContain('is--row-drag-preview-source');
        expect(rowsAfterReversingDrag[1].classes()).toContain('is--row-placeholder');

        await addressHandlingWrapper.vm.onDrop();
        await flushPromises();

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/company',
            'symbol/dash',
            'address/department',
        ]);

        await dragRow(0, 3);
        await addressHandlingWrapper.vm.onDrop();
        await flushPromises();

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/first_name',
            'address/last_name',
        ]);
        expect(wrapper.vm.country.addressFormat[1]).toEqual([
            'address/street',
        ]);
        expect(wrapper.vm.country.addressFormat[2]).toEqual([
            'address/zipcode',
            'address/city',
        ]);
        expect(wrapper.vm.country.addressFormat[3]).toEqual([
            'address/company',
            'symbol/dash',
            'address/department',
        ]);
        expect(addressHandlingWrapper.vm.rowDragPreview).toBeNull();

        const rowsAfterDrop = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(rowsAfterDrop).toHaveLength(5);
        rowsAfterDrop.forEach((row) => {
            expect(row.classes()).not.toContain('is--row-placeholder');
            expect(row.classes()).not.toContain('is--row-drag-preview-source');
        });

        await dragRow(3, 0);
        await addressHandlingWrapper.vm.onDrop();
        await flushPromises();

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/company',
            'symbol/dash',
            'address/department',
        ]);

        await dragRow(0, 2);

        const rowsDuringThirdDrag = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(rowsDuringThirdDrag).toHaveLength(6);
        expect(rowsDuringThirdDrag[0].classes()).toContain('is--row-drag-preview-source');
        expect(rowsDuringThirdDrag[3].classes()).toContain('is--row-placeholder');
    });

    it('should use the line position when dragging a row over a nested snippet drop zone', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);
        await addressHandlingWrapper.vm.onDragStart({
            data: {
                index: 0,
                snippet: wrapper.vm.country.addressFormat[0],
            },
        });
        await addressHandlingWrapper.vm.onDragEnter(
            {
                index: 0,
                snippet: wrapper.vm.country.addressFormat[0],
            },
            {
                index: 0,
                linePosition: 2,
                snippet: 'address/street',
            },
        );
        await flushPromises();

        const rowsWhileDragging = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(addressHandlingWrapper.vm.rowDragPreview.targetIndex).toBe(3);
        expect(rowsWhileDragging[3].classes()).toContain('is--row-placeholder');

        await addressHandlingWrapper.vm.onDrop();
        await flushPromises();

        expect(wrapper.vm.country.addressFormat[2]).toEqual([
            'address/company',
            'symbol/dash',
            'address/department',
        ]);
    });

    it('should be able to add a new snippet to another line on dragging', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/company',
            'symbol/dash',
            'address/department',
        ]);
        expect(wrapper.vm.country.addressFormat[1]).toEqual([
            'address/first_name',
            'address/last_name',
        ]);

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);
        await addressHandlingWrapper.vm.onDropEnd(0, {
            dragData: {
                index: 2,
                linePosition: 0,
                snippet: 'address/department',
            },
            dropData: {
                index: 1,
                snippet: [
                    'address/first_name',
                    'address/last_name',
                ],
            },
        });
        await flushPromises();

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/company',
            'symbol/dash',
        ]);

        expect(wrapper.vm.country.addressFormat[1]).toEqual([
            'address/first_name',
            'address/last_name',
            'address/department',
        ]);
    });

    it('should preview and move a snippet to the target position in another line', async () => {
        wrapper = await createWrapper(
            [
                'country.editor',
            ],
            {
                addressFormat: [
                    [
                        'address/company',
                        'symbol/dash',
                        'address/department',
                    ],
                    [
                        'address/first_name',
                        'address/last_name',
                    ],
                ],
            },
        );
        await flushPromises();

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);
        await addressHandlingWrapper.vm.onSnippetDragEnter({
            dragData: {
                index: 2,
                linePosition: 0,
                snippet: 'address/department',
            },
            dropData: {
                index: 1,
                snippet: [
                    'address/first_name',
                    'address/last_name',
                ],
            },
        });

        expect(addressHandlingWrapper.vm.snippetDragPreview).toBeNull();

        await addressHandlingWrapper.vm.onSnippetDragEnter({
            dragData: {
                index: 2,
                linePosition: 0,
                snippet: 'address/department',
            },
            dropData: {
                index: 1,
                linePosition: 1,
                snippet: 'address/last_name',
                targetIndex: 1,
            },
        });
        await flushPromises();

        const targetRowItems = wrapper
            .findAll('.sw-multi-snippet-drag-and-drop')[1]
            .findAll('.sw-select-selection-list > li');

        expect(targetRowItems[1].classes()).toContain('sw-multi-snippet-drag-and-drop__placeholder');

        await addressHandlingWrapper.vm.onDropEnd(0, {
            dragData: {
                index: 2,
                linePosition: 0,
                snippet: 'address/department',
            },
            dropData: {
                index: 1,
                linePosition: 1,
                snippet: 'address/last_name',
                targetIndex: 1,
            },
        });

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/company',
            'symbol/dash',
        ]);
        expect(wrapper.vm.country.addressFormat[1]).toEqual([
            'address/first_name',
            'address/department',
            'address/last_name',
        ]);
    });

    it('should move snippets before the target snippet in different lines', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.setProps({
            country: {
                addressFormat: [
                    [
                        'address/company',
                        'symbol/dash',
                        'address/department',
                    ],
                    [
                        'address/first_name',
                        'address/last_name',
                    ],
                ],
            },
        });
        await flushPromises();

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);
        await addressHandlingWrapper.vm.onDropEnd(1, {
            dragData: {
                index: 1,
                linePosition: 1,
                snippet: 'address/last_name',
            },
            dropData: {
                index: 2,
                linePosition: 0,
                snippet: 'address/department',
            },
        });

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/company',
            'symbol/dash',
            'address/last_name',
            'address/department',
        ]);
        expect(wrapper.vm.country.addressFormat[1]).toEqual([
            'address/first_name',
        ]);
    });

    it('should be able to preview formatting with the customer', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        expect(wrapper.find('.sw-settings-country-preview-template__content').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-country-address-handling__preview-separator').exists()).toBe(true);

        const selection = wrapper.get('.sw-entity-single-select');

        await selection.get('input').trigger('click');

        await flushPromises();

        const selectResult = wrapper.get('.sw-select-result-list-popover-wrapper');

        await selectResult.findAll('li')[0].trigger('click');

        await flushPromises();

        const previewTemplate = wrapper.get('.sw-settings-country-preview-template__content');

        expect(previewTemplate.html()).toBe(
            '<div class="sw-settings-country-preview-template__content">Christa Stracke<br> \\n \\n Philip Inlet<br> \\n \\n \\n \\n 22005-3637 New Marilyneside<br> \\n \\n Moldova (Republic of)<br><br></div>',
        );
    });

    it('should update the preview when the address markup changes after selecting a customer', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        await wrapper.get('.sw-entity-single-select input').trigger('click');
        await flushPromises();

        await wrapper.get('.sw-select-result-list-popover-wrapper').findAll('li')[0].trigger('click');
        await flushPromises();

        renderMock.mockResolvedValueOnce({
            rendered: 'Updated preview',
        });

        const addressHandlingWrapper = wrapper.findComponent(stubs['sw-settings-country-address-handling']);

        addressHandlingWrapper.vm.change(0, ['address/country']);
        await flushPromises();

        expect(renderMock).toHaveBeenCalledTimes(2);
        expect(renderMock.mock.calls.at(-1)[1][0]).toEqual(['address/country']);
        expect(wrapper.get('.sw-settings-country-preview-template__content').html()).toBe(
            '<div class="sw-settings-country-preview-template__content">Updated preview</div>',
        );
    });

    it('should be able to revert address to the default', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.setProps({
            country: { addressFormat },
        });
        await flushPromises();

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet).toHaveLength(5);

        // Open the context menu
        const contextButton = swMultiSnippet[0].find('.sw-context-button__button');
        await contextButton.trigger('click');
        await flushPromises();

        const menuContextButton = swMultiSnippet[0].findAll('.sw-context-menu-item')[5];

        await menuContextButton.trigger('click');

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet).toHaveLength(4);

        const buttonReset = wrapper.find('.sw-settings-country-address-handling__button-reset');

        await buttonReset.trigger('click');

        await wrapper.vm.$nextTick();

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet).toHaveLength(5);
    });
});
