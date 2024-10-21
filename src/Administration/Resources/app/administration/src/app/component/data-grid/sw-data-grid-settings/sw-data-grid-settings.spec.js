/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

describe('components/data-grid/sw-data-grid-settings', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('sw-data-grid-settings', { sync: true }), {
            props: {
                columns: [
                    { property: 'name', label: 'Name' },
                    { property: 'company', label: 'Company' },
                    { property: 'number', label: 'Number' },
                    { property: 'date', label: 'Date' },
                    { property: 'address', label: 'Address' },
                ],
                compact: true,
                previews: false,
                enablePreviews: true,
                disabled: false,
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-context-button': true,
                    'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                    'sw-switch-field': await wrapTestComponent('sw-switch-field', { sync: true }),
                    'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-button': await wrapTestComponent('sw-button', {
                        sync: true,
                    }),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                    'sw-icon': true,
                    'sw-context-menu-divider': true,
                    'sw-button-group': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-loader': true,
                    'router-link': true,
                },
            },
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should change value of compact based on prop', async () => {
        const switchButton = wrapper.findAll('.sw-field--switch__input input');
        expect(switchButton[0].element.checked).toBe(true);
    });

    it('should change value of previews based on prop', async () => {
        const switchButton = wrapper.findAll('.sw-field--switch__input input');
        expect(switchButton[1].element.checked).toBe(false);
    });

    it('should render a row for each item in column prop', async () => {
        const rows = wrapper.findAll('.sw-data-grid__settings-column-item');
        expect(rows).toHaveLength(5);
    });

    it('should order columns correctly', async () => {
        const expectOrder = (expectedColumns) => {
            const columns = wrapper.findAll('.sw-data-grid__settings-column-list .sw-field__label');

            expectedColumns.forEach((column, index) => {
                expect(columns.at(index).text()).toBe(column);
            });
        };

        expectOrder([
            'Name',
            'Company',
            'Number',
            'Date',
            'Address',
        ]);

        // move company from 1 to 2
        let companyDownButton = wrapper.find('.sw-data-grid__settings-item--1 .sw-button.down');
        await companyDownButton.trigger('click');

        expect(wrapper.emitted('change-column-order')[0]).toEqual([
            1,
            2,
        ]);

        await wrapper.setProps({
            columns: [
                { property: 'name', label: 'Name' },
                { property: 'number', label: 'Number' },
                { property: 'company', label: 'Company' },
                { property: 'date', label: 'Date' },
                { property: 'address', label: 'Address' },
            ],
        });

        expectOrder([
            'Name',
            'Number',
            'Company',
            'Date',
            'Address',
        ]);

        // move company from 2 to 3
        companyDownButton = wrapper.find('.sw-data-grid__settings-item--2 .sw-button.down');
        await companyDownButton.trigger('click');

        expect(wrapper.emitted('change-column-order')[1]).toEqual([
            2,
            3,
        ]);

        await wrapper.setProps({
            columns: [
                { property: 'name', label: 'Name' },
                { property: 'number', label: 'Number' },
                { property: 'date', label: 'Date' },
                { property: 'company', label: 'Company' },
                { property: 'address', label: 'Address' },
            ],
        });

        expectOrder([
            'Name',
            'Number',
            'Date',
            'Company',
            'Address',
        ]);

        // move date from 2 to 1
        const dateUpButton = wrapper.find('.sw-data-grid__settings-item--2 .sw-button:not(.down)');
        await dateUpButton.trigger('click');

        expect(wrapper.emitted('change-column-order')[2]).toEqual([
            2,
            1,
        ]);

        await wrapper.setProps({
            columns: [
                { property: 'name', label: 'Name' },
                { property: 'date', label: 'Date' },
                { property: 'number', label: 'Number' },
                { property: 'company', label: 'Company' },
                { property: 'address', label: 'Address' },
            ],
        });

        expectOrder([
            'Name',
            'Date',
            'Number',
            'Company',
            'Address',
        ]);
    });
});
