import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/module/sw-settings-country/component/sw-settings-country-address-handling';
import 'src/module/sw-settings-country/component/sw-settings-country-preview-template';
import 'src/app/component/base/sw-card';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/module/sw-settings-country/component/sw-multi-snippet-drag-and-drop';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-label';
import 'src/app/component/base/sw-button';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';

function createWrapper(privileges = [], customPropsData = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.directive('droppable', {});
    localVue.directive('draggable', {});

    return shallowMount(Shopware.Component.build('sw-settings-country-address-handling'), {
        localVue,

        mocks: {
            $tc: key => key,
            $route: {
                params: {
                    id: 'id'
                }
            },
            $device: {
                getSystemKey: () => {},
                onResize: () => {}
            }
        },

        propsData: {
            country: {
                isNew: () => false,
                ...customPropsData
            },
            isLoading: false
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([{
                            id: 'id',
                            firstName: 'Y',
                            lastName: 'Tran',
                            defaultBillingAddress: {
                                company: 'Shopware',
                                department: 'IT',
                                street: 'Ebbinghoff 10',
                                zipcode: '48624',
                                city: 'Schöppingen',
                                country: {
                                    name: 'Germany'
                                }
                            }
                        }]);
                    },
                }),
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            userInputSanitizeService: {},
            feature: {
                isActive: () => true
            }
        },

        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>'
            },
            'sw-container': true,
            'sw-ignore-class': true,
            'sw-text-field': true,
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-field-error': true,
            'sw-help-text': true,
            'sw-icon': true,
            'sw-extension-component-section': true,
            'sw-multi-snippet-drag-and-drop': Shopware.Component.build('sw-multi-snippet-drag-and-drop'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': true,
            'sw-label': Shopware.Component.build('sw-label'),
            'sw-settings-country-preview-template': Shopware.Component.build('sw-settings-country-preview-template'),
            'sw-settings-country-new-snippet-modal': {
                template: `
                    <div class="sw-modal sw-settings-country-new-snippet-modal">
                        <slot name="modal-header" @click.prevent="$emit('modal-close')"></slot>
                        <slot></slot>
                        <slot name="modal-footer"></slot>
                    </div>`
            },
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-context-menu-item': {
                template: `
                    <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                        <slot></slot>
                    </div>`
            },
            'sw-entity-single-select': Shopware.Component.build('sw-entity-single-select'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': true,
            'sw-loader': true,
        }
    });
}

enableAutoDestroy(afterEach);

describe('module/sw-settings-country/component/sw-settings-country-address-handling', () => {
    beforeAll(() => {
        Shopware.State.get('session').currentUser = {};
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to edit the address handling tab', async () => {
        const wrapper = await createWrapper([
            'country.editor'
        ],);

        const countryForceStateInRegistrationField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelForceStateInRegistration"]'
        );

        const countryPostalCodeRequiredField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelPostalCodeRequired"]'
        );

        const countryCheckPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckPostalCodePattern"]'
        );

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]'
        );

        expect(countryForceStateInRegistrationField.attributes().disabled).toBeUndefined();
        expect(countryPostalCodeRequiredField.attributes().disabled).toBeUndefined();
        expect(countryCheckPostalCodePatternField.attributes().disabled).toBeUndefined();
        expect(countryCheckAdvancedPostalCodePatternField.attributes().disabled).toBeTruthy();
    });

    it('should not able to edit the address handling tab', async () => {
        const wrapper = createWrapper([], {
            checkAdvancedPostalCodePattern: true,
        });

        await wrapper.vm.$nextTick();

        const countryForceStateInRegistrationField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelForceStateInRegistration"]'
        );

        const countryPostalCodeRequiredField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelPostalCodeRequired"]'
        );

        const countryCheckPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckPostalCodePattern"]'
        );

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]'
        );

        expect(countryForceStateInRegistrationField.attributes().disabled).toBeTruthy();
        expect(countryPostalCodeRequiredField.attributes().disabled).toBeTruthy();
        expect(countryCheckPostalCodePatternField.attributes().disabled).toBeTruthy();
        expect(countryCheckAdvancedPostalCodePatternField.attributes().disabled).toBeTruthy();
    });

    it('should be able to toggle advanced postal code pattern', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        await wrapper.setProps({
            country: {
                checkPostalCodePattern: true,
            }
        });

        expect(wrapper.find('.advanced-postal-code .is--disabled').exists()).toBeTruthy();

        const checkAdvancedPostalCodePatternField = wrapper.findAll('.sw-field--switch').at(3);
        await checkAdvancedPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.find(
            '.advanced-postal-code .is--disabled'
        ).exists()).toBeFalsy();
    });

    it('should be not able to toggle advanced postal code pattern', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        await wrapper.setProps({
            country: {
                checkAdvancedPostalCodePattern: true,
                checkPostalCodePattern: true,
            }
        });

        expect(wrapper.find(
            '.advanced-postal-code .is--disabled'
        ).exists()).toBeFalsy();

        const checkPostalCodePatternField = wrapper.findAll('.sw-field--switch').at(2);

        await checkPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.find('.advanced-postal-code .is--disabled').exists()).toBeTruthy();

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]'
        );

        expect(countryCheckAdvancedPostalCodePatternField.attributes().disabled).toBeTruthy();
    });

    it('should revert advanced postal code pattern when toggle on Advanced validation rules', async () => {
        const wrapper = createWrapper(['country.editor']);

        await wrapper.setProps({
            country: {
                checkPostalCodePattern: true,
                checkAdvancedPostalCodePattern: true,
                advancedPostalCodePattern: '/^\\d{5}(?:[- ]?\\d{4})?$/',
            }
        });


        const checkPostalCodePatternField = wrapper.findAll('.sw-field--switch').at(2);

        await checkPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.vm.country.advancedPostalCodePattern).toBeNull();

        await checkPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        const checkAdvancedPostalCodePattern = wrapper.findAll('.sw-field--switch').at(3);

        await checkAdvancedPostalCodePattern
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.vm.country.advancedPostalCodePattern).toEqual('/^\\d{5}(?:[- ]?\\d{4})?$/');
    });

    it('should able to show the modal with insert new snippet', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        expect(wrapper.find('.sw-settings-country-new-snippet-modal').exists()).toBeFalsy();
        expect(wrapper.vm.currentPosition).toEqual(null);
        expect(wrapper.vm.isOpenModal).toBe(false);

        const swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop').at(0);

        const menuContextButton = swMultiSnippet.findAll('.sw-context-menu-item').at(0);

        await menuContextButton.trigger('click');
        expect(wrapper.find('.sw-settings-country-new-snippet-modal').exists()).toBeTruthy();
        expect(wrapper.vm.currentPosition).toEqual(0);
        expect(wrapper.vm.isOpenModal).toBe(true);
    });

    it('should be able to add a new row above than current row', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet.length).toEqual(5);
        expect(swMultiSnippet.at(0).findAll('.sw-select-selection-list > li').length).toEqual(4);

        const menuContextButton = swMultiSnippet.at(0).findAll('.sw-context-menu-item').at(1);

        await menuContextButton.trigger('click');

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet.length).toEqual(6);
        expect(swMultiSnippet.at(0).findAll('.sw-select-selection-list > li').length).toEqual(1);
    });

    it('should be able to add a new row below than current row', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet.length).toEqual(5);
        expect(swMultiSnippet.at(0).findAll('.sw-select-selection-list > li').length).toEqual(4);
        expect(swMultiSnippet.at(1).findAll('.sw-select-selection-list > li').length).toEqual(3);

        const menuContextButton = swMultiSnippet.at(0).findAll('.sw-context-menu-item').at(2);

        await menuContextButton.trigger('click');

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet.length).toEqual(6);
        expect(swMultiSnippet.at(0).findAll('.sw-select-selection-list > li').length).toEqual(4);
        expect(swMultiSnippet.at(1).findAll('.sw-select-selection-list > li').length).toEqual(1);
    });

    it('should be able to move the current row to the top', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(wrapper.vm.advancedAddressFormat).toEqual([
            [
                { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
                { value: 'snippet.custom', label: '-' },
                { value: 'customer.defaultBillingAddress.department', label: 'Department' },
            ],
            [{ value: 'customer.firstName', label: 'First name' }, { value: 'customer.lastName', label: 'Last name' }],
            [{ value: 'customer.defaultBillingAddress.street', label: 'Street name' }],
            [
                { value: 'customer.defaultBillingAddress.zipcode', label: 'Zip code' },
                { value: 'customer.defaultBillingAddress.city', label: 'City' },
            ],
            [{ value: 'customer.defaultBillingAddress.country.name', label: 'Country' }],
        ]);

        expect(swMultiSnippet.at(0).findAll('.sw-select-selection-list > li').length).toEqual(4);
        expect(swMultiSnippet.at(4).findAll('.sw-select-selection-list > li').length).toEqual(2);

        const menuContextButton = swMultiSnippet.at(4).findAll('.sw-context-menu-item').at(3);

        await menuContextButton.trigger('click');

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(wrapper.vm.advancedAddressFormat).toEqual([
            [{ value: 'customer.defaultBillingAddress.country.name', label: 'Country' }],
            [
                { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
                { value: 'snippet.custom', label: '-' },
                { value: 'customer.defaultBillingAddress.department', label: 'Department' },
            ],
            [{ value: 'customer.firstName', label: 'First name' }, { value: 'customer.lastName', label: 'Last name' }],
            [{ value: 'customer.defaultBillingAddress.street', label: 'Street name' }],
            [
                { value: 'customer.defaultBillingAddress.zipcode', label: 'Zip code' },
                { value: 'customer.defaultBillingAddress.city', label: 'City' },
            ],
        ]);
        expect(swMultiSnippet.at(0).findAll('.sw-select-selection-list > li').length).toEqual(2);
        expect(swMultiSnippet.at(1).findAll('.sw-select-selection-list > li').length).toEqual(4);
        expect(swMultiSnippet.at(4).findAll('.sw-select-selection-list > li').length).toEqual(3);
    });

    it('should be able to move the current row to the bottom', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(wrapper.vm.advancedAddressFormat).toEqual([
            [
                { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
                { value: 'snippet.custom', label: '-' },
                { value: 'customer.defaultBillingAddress.department', label: 'Department' },
            ],
            [{ value: 'customer.firstName', label: 'First name' }, { value: 'customer.lastName', label: 'Last name' }],
            [{ value: 'customer.defaultBillingAddress.street', label: 'Street name' }],
            [
                { value: 'customer.defaultBillingAddress.zipcode', label: 'Zip code' },
                { value: 'customer.defaultBillingAddress.city', label: 'City' },
            ],
            [{ value: 'customer.defaultBillingAddress.country.name', label: 'Country' }],
        ]);

        expect(swMultiSnippet.at(1).findAll('.sw-select-selection-list > li').length).toEqual(3);
        expect(swMultiSnippet.at(4).findAll('.sw-select-selection-list > li').length).toEqual(2);

        const menuContextButton = swMultiSnippet.at(1).findAll('.sw-context-menu-item').at(4);

        await menuContextButton.trigger('click');

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(wrapper.vm.advancedAddressFormat).toEqual([
            [
                { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
                { value: 'snippet.custom', label: '-' },
                { value: 'customer.defaultBillingAddress.department', label: 'Department' },
            ],
            [{ value: 'customer.defaultBillingAddress.street', label: 'Street name' }],
            [
                { value: 'customer.defaultBillingAddress.zipcode', label: 'Zip code' },
                { value: 'customer.defaultBillingAddress.city', label: 'City' },
            ],
            [{ value: 'customer.defaultBillingAddress.country.name', label: 'Country' }],
            [{ value: 'customer.firstName', label: 'First name' }, { value: 'customer.lastName', label: 'Last name' }],
        ]);
        expect(swMultiSnippet.at(1).findAll('.sw-select-selection-list > li').length).toEqual(2);
        expect(swMultiSnippet.at(3).findAll('.sw-select-selection-list > li').length).toEqual(2);
        expect(swMultiSnippet.at(4).findAll('.sw-select-selection-list > li').length).toEqual(3);
    });

    it('should be able to delete the current row', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet.length).toEqual(5);

        const menuContextButton = swMultiSnippet.at(0).findAll('.sw-context-menu-item').at(5);

        await menuContextButton.trigger('click');

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet.length).toEqual(4);
    });

    it('should be able to sort the list on dragging', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        expect(wrapper.vm.advancedAddressFormat[0]).toEqual([
            { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
            { value: 'snippet.custom', label: '-' },
            { value: 'customer.defaultBillingAddress.department', label: 'Department' },
        ]);
        expect(wrapper.vm.advancedAddressFormat[1]).toEqual([
            { value: 'customer.firstName', label: 'First name' },
            { value: 'customer.lastName', label: 'Last name' },
        ]);

        await wrapper.setData({
            draggedItem: {
                index: 1,
                snippet: [
                    { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
                    { value: 'snippet.custom', label: '-' },
                    { value: 'customer.defaultBillingAddress.department', label: 'Department' },
                ]
            },
            droppedItem: {
                index: 0,
                snippet: [
                    { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
                    { value: 'snippet.custom', label: '-' },
                    { value: 'customer.defaultBillingAddress.department', label: 'Department' },
                ]
            }
        });

        await wrapper.vm.dragEnd();

        expect(wrapper.vm.advancedAddressFormat[0]).toEqual([
            { value: 'customer.firstName', label: 'First name' },
            { value: 'customer.lastName', label: 'Last name' },
        ]);
        expect(wrapper.vm.advancedAddressFormat[1]).toEqual([
            { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
            { value: 'snippet.custom', label: '-' },
            { value: 'customer.defaultBillingAddress.department', label: 'Department' },
        ]);
    });

    it('should be able to add a new snippet to another line on dragging', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        expect(wrapper.vm.advancedAddressFormat[0]).toEqual([
            { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
            { value: 'snippet.custom', label: '-' },
            { value: 'customer.defaultBillingAddress.department', label: 'Department' },
        ]);
        expect(wrapper.vm.advancedAddressFormat[1]).toEqual([
            { value: 'customer.firstName', label: 'First name' },
            { value: 'customer.lastName', label: 'Last name' }
        ]);

        await wrapper.vm.onDragEnd(
            0,
            {
                dragData: {
                    index: 2,
                    linePosition: 0,
                    snippet: {
                        label: 'Department',
                        value: 'customer.defaultBillingAddress.department'
                    }
                },
                dropData: {
                    index: 1,
                    snippet: [
                        { value: 'customer.firstName', label: 'First name' },
                        { value: 'customer.lastName', label: 'Last name' }
                    ]
                }
            }
        );

        expect(wrapper.vm.advancedAddressFormat[0]).toEqual([
            { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
            { value: 'snippet.custom', label: '-' },
        ]);
        expect(wrapper.vm.advancedAddressFormat[1]).toEqual([
            { value: 'customer.firstName', label: 'First name' },
            { value: 'customer.lastName', label: 'Last name' },
            { value: 'customer.defaultBillingAddress.department', label: 'Department' },
        ]);
    });

    it('should be able to swap on the same line on dragging', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        expect(wrapper.vm.advancedAddressFormat[0][1]).toEqual({ value: 'snippet.custom', label: '-' });
        expect(wrapper.vm.advancedAddressFormat[0][2])
            .toEqual({ value: 'customer.defaultBillingAddress.department', label: 'Department' });

        await wrapper.vm.onDragEnd(
            0,
            {
                dragData: {
                    index: 2,
                    linePosition: 0,
                    snippet: {
                        label: 'Department',
                        value: 'customer.defaultBillingAddress.department'
                    }
                },
                dropData: {
                    index: 1,
                    linePosition: 0,
                    snippet: [
                        { value: 'snippet.custom', label: '-' },
                    ]
                }
            }
        );

        expect(wrapper.vm.advancedAddressFormat[0][1])
            .toEqual({ value: 'customer.defaultBillingAddress.department', label: 'Department' });
        expect(wrapper.vm.advancedAddressFormat[0][2]).toEqual({ value: 'snippet.custom', label: '-' });
    });

    it('should be able to preview formatting with the customer', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        const selection = wrapper.find('.sw-entity-single-select');

        await selection.find('input').trigger('click');

        await wrapper.vm.$nextTick();

        const selectResult = wrapper.find('.sw-select-result-list-popover-wrapper');

        await selectResult.findAll('li').at(0).trigger('click');

        const previewTemplate = wrapper.find('.sw-settings-country-preview-template');

        const attributes = previewTemplate.findAll('span');

        expect(attributes.at(0).text()).toEqual('Shopware - IT');
        expect(attributes.at(1).text()).toEqual('Y Tran');
        expect(attributes.at(2).text()).toEqual('Ebbinghoff 10');
        expect(attributes.at(3).text()).toEqual('48624 Schöppingen');
        expect(attributes.at(4).text()).toEqual('Germany');
    });

    it('should be able to reset markup', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        let swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet.length).toEqual(5);
        expect(swMultiSnippet.at(0).findAll('.sw-select-selection-list > li').length).toEqual(4);
        expect(swMultiSnippet.at(1).findAll('.sw-select-selection-list > li').length).toEqual(3);

        const menuContextButton = swMultiSnippet.at(0).findAll('.sw-context-menu-item').at(2);

        await menuContextButton.trigger('click');

        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet.length).toEqual(6);
        expect(swMultiSnippet.at(0).findAll('.sw-select-selection-list > li').length).toEqual(4);
        expect(swMultiSnippet.at(1).findAll('.sw-select-selection-list > li').length).toEqual(1);

        const resetMarkupButton = wrapper.find('.sw-settings-country-address-handling__button-reset');

        await resetMarkupButton.trigger('click');
        swMultiSnippet = wrapper.findAll('.sw-multi-snippet-drag-and-drop');

        expect(swMultiSnippet.length).toEqual(5);
        expect(swMultiSnippet.at(0).findAll('.sw-select-selection-list > li').length).toEqual(4);
        expect(swMultiSnippet.at(1).findAll('.sw-select-selection-list > li').length).toEqual(3);
    });
});
