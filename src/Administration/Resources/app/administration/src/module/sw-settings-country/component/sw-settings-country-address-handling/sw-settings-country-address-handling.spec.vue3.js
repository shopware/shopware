import { mount } from '@vue/test-utils_v3';

const addressFormat = [
    ['address/company', 'symbol/dash', 'address/department'],
    ['address/first_name', 'address/last_name'],
    ['address/street'],
    ['address/zipcode', 'address/city'],
    ['address/country'],
];

async function createWrapper(privileges = [], customPropsData = {}) {
    return mount(await wrapTestComponent('sw-settings-country-address-handling', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            directives: {
                tooltip: {},
                droppable: {},
                draggable: {},
            },
            mocks: {
                $tc: key => key,
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
                            return Promise.resolve([{
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
                            }]);
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    },
                },
                customSnippetApiService: {
                    snippets: () => {
                        return Promise.resolve({
                            data: ['symbol/dash', 'symbol/comma', 'address/country_state', 'address/salutation'],
                        });
                    },

                    render: () => Promise.resolve({
                        rendered: 'Christa Stracke<br/> \\n \\n Philip Inlet<br/> \\n \\n \\n \\n 22005-3637 New Marilyneside<br/> \\n \\n Moldova (Republic of)<br/><br/>',
                    }),
                },
                countryApiService: {
                    defaultCountryAddressFormat: () => Promise.resolve({
                        data: addressFormat,
                    }),
                },
                userInputSanitizeService: {},
            },

            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot></slot></div>',
                },
                'sw-container': true,
                'sw-ignore-class': true,
                'sw-text-field': true,
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-field-error': true,
                'sw-help-text': true,
                'sw-icon': true,
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
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-context-menu-item': {
                    template: `
                    <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                        <slot></slot>
                    </div>`,
                },
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-popover': {
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
            },
        },

        props: {
            country: {
                isNew: () => false,
                addressFormat,
                ...customPropsData,
            },
            isLoading: false,
        },
    });
}

describe('module/sw-settings-country/component/sw-settings-country-address-handling', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.get('session').currentUser = {};
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to edit the address handling tab', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ], {
            defaultPostalCodePattern: '\\d{5}',
        });
        await flushPromises();

        const countryForceStateInRegistrationField = wrapper.find(
            '.sw-field[label="sw-settings-country.detail.labelForceStateInRegistration"]',
        );

        const countryPostalCodeRequiredField = wrapper.find(
            '.sw-field[label="sw-settings-country.detail.labelPostalCodeRequired"]',
        );

        const countryCheckPostalCodePatternField = wrapper.find(
            '.sw-field[label="sw-settings-country.detail.labelCheckPostalCodePattern"]',
        );

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            '.sw-field[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]',
        );

        expect(countryForceStateInRegistrationField.classes('is--disabled')).toBeFalsy();
        expect(countryPostalCodeRequiredField.classes('is--disabled')).toBeFalsy();
        expect(countryCheckPostalCodePatternField.classes('is--disabled')).toBeFalsy();
        expect(countryCheckAdvancedPostalCodePatternField.classes('is--disabled')).toBeTruthy();
    });

    it('should not able to edit the address handling tab', async () => {
        wrapper = await createWrapper([], {
            checkAdvancedPostalCodePattern: true,
        });

        await flushPromises();

        const countryForceStateInRegistrationField = wrapper.find(
            '.sw-field[label="sw-settings-country.detail.labelForceStateInRegistration"]',
        );

        const countryPostalCodeRequiredField = wrapper.find(
            '.sw-field[label="sw-settings-country.detail.labelPostalCodeRequired"]',
        );

        const countryCheckPostalCodePatternField = wrapper.find(
            '.sw-field[label="sw-settings-country.detail.labelCheckPostalCodePattern"]',
        );

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            '.sw-field[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]',
        );

        expect(countryForceStateInRegistrationField.classes('is--disabled')).toBeTruthy();
        expect(countryPostalCodeRequiredField.classes('is--disabled')).toBeTruthy();
        expect(countryCheckPostalCodePatternField.classes('is--disabled')).toBeTruthy();
        expect(countryCheckAdvancedPostalCodePatternField.classes('is--disabled')).toBeTruthy();
    });

    it('should be able to toggle advanced postal code pattern', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ], {
            defaultPostalCodePattern: '\\d{5}',
        });

        await wrapper.setProps({
            country: {
                ...wrapper.vm.country,
                checkPostalCodePattern: false,
            },
        });

        await flushPromises();

        expect(wrapper.find('.advanced-postal-code > .sw-field--switch.is--disabled').exists()).toBeTruthy();

        const checkAdvancedPostalCodePatternField = wrapper.findAll('.sw-settings-country-address-handling__option-items')[2];
        await checkAdvancedPostalCodePatternField
            .find('.sw-field--switch__input input')
            .setChecked();

        await flushPromises();

        expect(wrapper.find('.advanced-postal-code > .sw-field--switch.is--disabled').exists()).toBeFalsy();
    });

    it('should be not able to toggle advanced postal code pattern', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ], {
            defaultPostalCodePattern: '\\d{5}',
        });

        await wrapper.setProps({
            country: {
                ...wrapper.vm.country,
                checkAdvancedPostalCodePattern: true,
                checkPostalCodePattern: true,
            },
        });

        await flushPromises();

        expect(wrapper.find('.advanced-postal-code > .sw-field--switch.is--disabled').exists()).toBeFalsy();

        const checkPostalCodePatternField = wrapper.findAll('.sw-field--switch')[2];

        await checkPostalCodePatternField
            .find('.sw-field--switch__input input')
            .setChecked(false);

        await flushPromises();

        expect(wrapper.find('.advanced-postal-code > .sw-field--switch.is--disabled').exists()).toBeTruthy();

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            '.sw-field[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]',
        );

        expect(countryCheckAdvancedPostalCodePatternField.classes('is--disabled')).toBeTruthy();
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

        const checkPostalCodePatternField = wrapper.findAll('.sw-field--switch')[2];

        await checkPostalCodePatternField
            .find('.sw-field--switch__input input')
            .setChecked(false);

        await flushPromises();

        expect(wrapper.vm.country.checkAdvancedPostalCodePattern).toBe(false);

        await checkPostalCodePatternField
            .find('.sw-field--switch__input input')
            .setChecked();

        await flushPromises();

        const checkAdvancedPostalCodePattern = wrapper.findAll('.sw-field--switch')[3];

        await checkAdvancedPostalCodePattern
            .find('.sw-field--switch__input input')
            .setChecked();

        await flushPromises();

        expect(wrapper.vm.country.advancedPostalCodePattern).toBe('/^\\d{5}(?:[- ]?\\d{4})?$/');
    });

    it('should disable postal code validation', async () => {
        // eslint-disable-next-line no-restricted-syntax
        for (const prop of [
            {
                checkPostalCodePattern: true,
                checkAdvancedPostalCodePattern: true,
            },
            {},
        ]) {
            // eslint-disable-next-line no-await-in-loop
            wrapper = await createWrapper(['country.editor'], prop);
            // eslint-disable-next-line no-await-in-loop
            await flushPromises();

            const countryCheckPostalCodePatternField = wrapper.find(
                '.sw-field[label="sw-settings-country.detail.labelCheckPostalCodePattern"]',
            );

            const countryCheckAdvancedPostalCodePatternField = wrapper.find(
                '.sw-field[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]',
            );

            expect(countryCheckPostalCodePatternField.classes('is--disabled')).toBeTruthy();
            expect(countryCheckAdvancedPostalCodePatternField.classes('is--disabled')).toBeFalsy();
        }
    });

    it('should able to show the modal with insert new snippet', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        expect(wrapper.find('.sw-settings-country-new-snippet-modal').exists()).toBeFalsy();
        expect(wrapper.vm.currentPosition).toBeNull();
        expect(wrapper.vm.isOpenModal).toBe(false);

        const swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop')[0];

        const menuContextButton = swMultiSnippet.findAll('.sw-context-menu-item')[0];

        await menuContextButton.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-settings-country-new-snippet-modal').exists()).toBeTruthy();
        expect(wrapper.vm.currentPosition).toBe(0);
        expect(wrapper.vm.isOpenModal).toBe(true);
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
            ['address/company', 'symbol/dash', 'address/department'],
            ['address/first_name', 'address/last_name'],
            ['address/street'],
            ['address/zipcode', 'address/city'],
            ['address/country'],
        ]);

        expect(swMultiSnippet[0].findAll('.sw-select-selection-list > li')).toHaveLength(4);
        expect(swMultiSnippet[4].findAll('.sw-select-selection-list > li')).toHaveLength(2);

        const menuContextButton = swMultiSnippet[4].findAll('.sw-context-menu-item')[3];

        await menuContextButton.trigger('click');
        await flushPromises();

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(wrapper.vm.addressFormat).toEqual([
            ['address/country'],
            ['address/company', 'symbol/dash', 'address/department'],
            ['address/first_name', 'address/last_name'],
            ['address/street'],
            ['address/zipcode', 'address/city'],
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
            ['address/company', 'symbol/dash', 'address/department'],
            ['address/first_name', 'address/last_name'],
            ['address/street'],
            ['address/zipcode', 'address/city'],
            ['address/country'],
        ]);

        expect(swMultiSnippet[1].findAll('.sw-select-selection-list > li')).toHaveLength(3);
        expect(swMultiSnippet[4].findAll('.sw-select-selection-list > li')).toHaveLength(2);

        const menuContextButton = swMultiSnippet[1].findAll('.sw-context-menu-item')[4];

        await menuContextButton.trigger('click');
        await flushPromises();

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(wrapper.vm.country.addressFormat).toEqual([
            ['address/company', 'symbol/dash', 'address/department'],
            ['address/street'],
            ['address/zipcode', 'address/city'],
            ['address/country'],
            ['address/first_name', 'address/last_name'],
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

        await wrapper.vm.onDragStart({
            data: {
                index: 0,
                snippet: ['address/company', 'symbol/dash', 'address/department'],
            },
        });

        expect(wrapper.vm.draggedItem).toEqual({
            index: 0,
            snippet: ['address/company', 'symbol/dash', 'address/department'],
        });
    });

    it('should not be able to save config with an invalid item when ending drag', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        expect(wrapper.vm.draggedItem).toBeNull();
        expect(wrapper.vm.droppedItem).toBeNull();

        await wrapper.vm.onDragEnter(null, null);
        await flushPromises();

        expect(wrapper.vm.draggedItem).toBeNull();
        expect(wrapper.vm.droppedItem).toBeNull();

        await wrapper.vm.onDragStart({
            data: {
                index: 0,
                snippet: ['address/company', 'symbol/dash', 'address/department'],
            },
        });
        await flushPromises();

        expect(wrapper.vm.draggedItem).toEqual({
            index: 0,
            snippet: ['address/company', 'symbol/dash', 'address/department'],
        });

        await wrapper.vm.onDragEnter({
            index: 0,
            snippet: ['address/company', 'symbol/dash', 'address/department'],
        }, null);

        expect(wrapper.vm.droppedItem).toBeNull();
    });

    it('should be able to save config when drag ends', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        expect(wrapper.vm.draggedItem).toBeNull();
        expect(wrapper.vm.droppedItem).toBeNull();

        await wrapper.vm.onDragStart({
            data: {
                index: 0,
                snippet: ['address/company', 'symbol/dash', 'address/department'],
            },
        });

        expect(wrapper.vm.draggedItem).toEqual({
            index: 0,
            snippet: ['address/company', 'symbol/dash', 'address/department'],
        });

        await wrapper.vm.onDragEnter({
            index: 0,
            snippet: ['address/company', 'symbol/dash', 'address/department'],
        }, {
            index: 1,
            snippet: ['address/company', 'symbol/dash', 'address/department'],
        });

        expect(wrapper.vm.droppedItem).toEqual({
            index: 1,
            snippet: ['address/company', 'symbol/dash', 'address/department'],
        });
    });

    it('should be able to sort the list on dragging', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/company', 'symbol/dash', 'address/department',
        ]);
        expect(wrapper.vm.country.addressFormat[1]).toEqual([
            'address/first_name', 'address/last_name',
        ]);

        await wrapper.setData({
            draggedItem: {
                index: 1,
                snippet: ['address/company', 'symbol/dash', 'address/department'],
            },
            droppedItem: {
                index: 0,
                snippet: ['address/company', 'symbol/dash', 'address/department'],
            },
        });

        await wrapper.vm.onDrop();

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/first_name', 'address/last_name',
        ]);
        expect(wrapper.vm.country.addressFormat[1]).toEqual([
            'address/company', 'symbol/dash', 'address/department',
        ]);
    });

    it('should be able to add a new snippet to another line on dragging', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        expect(wrapper.vm.country.addressFormat[0]).toEqual(
            ['address/company', 'symbol/dash', 'address/department'],
        );
        expect(wrapper.vm.country.addressFormat[1]).toEqual([
            'address/first_name', 'address/last_name',
        ]);

        await wrapper.vm.onDropEnd(
            0,
            {
                dragData: {
                    index: 2,
                    linePosition: 0,
                    snippet: 'address/department',
                },
                dropData: {
                    index: 1,
                    snippet: ['address/first_name', 'address/last_name'],
                },
            },
        );

        expect(wrapper.vm.country.addressFormat[0]).toEqual([
            'address/company', 'symbol/dash',
        ]);

        expect(wrapper.vm.country.addressFormat[1]).toEqual([
            'address/first_name', 'address/last_name', 'address/department',
        ]);
    });

    it('should be able to swap positions in different lines', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.setProps({
            country: {
                addressFormat: [
                    ['address/company', 'symbol/dash', 'address/department'],
                    ['address/first_name', 'address/last_name'],
                ],
            },
        });
        await flushPromises();

        expect(wrapper.vm.country.addressFormat[0][2]).toBe('address/department');
        expect(wrapper.vm.country.addressFormat[1][1]).toBe('address/last_name');

        await wrapper.vm.onDropEnd(
            1,
            {
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
            },
        );

        expect(wrapper.vm.country.addressFormat[0][2]).toBe('address/last_name');
        expect(wrapper.vm.country.addressFormat[1][1]).toBe('address/department');
    });

    it('should be able to preview formatting with the customer', async () => {
        wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        let previewTemplate = wrapper.find('.sw-settings-country-preview-template > div');

        expect(previewTemplate.html()).toBe('<div></div>');

        const selection = wrapper.find('.sw-entity-single-select');

        await selection.find('input').trigger('click');

        await flushPromises();

        const selectResult = wrapper.find('.sw-select-result-list-popover-wrapper');

        await selectResult.findAll('li')[0].trigger('click');

        await flushPromises();

        previewTemplate = wrapper.find('.sw-settings-country-preview-template > div');

        expect(previewTemplate.html()).toBe('<div>Christa Stracke<br> \\n \\n Philip Inlet<br> \\n \\n \\n \\n 22005-3637 New Marilyneside<br> \\n \\n Moldova (Republic of)<br><br></div>');
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
