/**
 * @sw-package framework
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
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
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

    it('should change value of compact based on prop', async () => {
        const switchButton = wrapper.findAll('.mt-switch input');
        expect(switchButton[0].element.checked).toBe(true);
    });

    it('should change value of previews based on prop', async () => {
        const switchButton = wrapper.findAll('.mt-switch input');
        expect(switchButton[1].element.checked).toBe(false);
    });

    it('should render a row for each item in column prop', async () => {
        const rows = wrapper.findAll('.sw-data-grid__settings-column-item');
        expect(rows).toHaveLength(5);
    });

    it('should order columns correctly', async () => {
        const expectOrder = (expectedColumns) => {
            const columns = wrapper.findAll('.sw-data-grid__settings-column-list .mt-field__label');

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
        let companyDownButton = wrapper.find('.sw-data-grid__settings-item--1 .mt-button.down');
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
        companyDownButton = wrapper.find('.sw-data-grid__settings-item--2 .mt-button.down');
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
        const dateUpButton = wrapper.find('.sw-data-grid__settings-item--2 .mt-button:not(.down)');
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

    describe('getColumnLabel', () => {
        const messages = {
            'en-GB': { 'sw-grid.column.name': 'Name (EN)' },
            'de-DE': { 'sw-grid.column.name': 'Name (DE)' },
        };

        async function createSettingsWrapper({ locale, $te, $t } = {}) {
            Shopware.Context.app.fallbackLocale = 'en-GB';

            return mount(await wrapTestComponent('sw-data-grid-settings', { sync: true }), {
                props: {
                    columns: [{ property: 'name', label: 'Name' }],
                    compact: true,
                    previews: false,
                    enablePreviews: true,
                    disabled: false,
                },
                global: {
                    renderStubDefaultSlot: true,
                    mocks: {
                        $te: $te ?? ((key, l) => Boolean(messages[l ?? locale]?.[key])),
                        $t: $t ?? ((key, l) => messages[l ?? locale]?.[key] ?? key),
                    },
                    stubs: {
                        'sw-context-button': true,
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
        }

        it('returns the translated label when the snippet exists in the current locale', async () => {
            const settings = await createSettingsWrapper({ locale: 'de-DE' });

            expect(settings.vm.getColumnLabel({ label: 'sw-grid.column.name' })).toBe('Name (DE)');
        });

        it('falls back to the fallback locale when the snippet is missing in the current locale', async () => {
            const settings = await createSettingsWrapper({ locale: 'fr-FR' });

            expect(settings.vm.getColumnLabel({ label: 'sw-grid.column.name' })).toBe('Name (EN)');
        });

        it('returns the raw label when neither current nor fallback locale has the snippet', async () => {
            const settings = await createSettingsWrapper({ locale: 'fr-FR' });

            expect(settings.vm.getColumnLabel({ label: 'Plain Text Label' })).toBe('Plain Text Label');
        });

        it('returns an empty string when the column has no label', async () => {
            const settings = await createSettingsWrapper({ locale: 'en-GB' });

            expect(settings.vm.getColumnLabel({})).toBe('');
            expect(settings.vm.getColumnLabel({ label: '' })).toBe('');
        });

        it('does not consult the fallback locale when no fallbackLocale is configured', async () => {
            const $te = jest.fn(() => false);
            const $t = jest.fn((key) => key);
            const settings = await createSettingsWrapper({ $te, $t });

            Shopware.Context.app.fallbackLocale = '';
            $te.mockClear();

            expect(settings.vm.getColumnLabel({ label: 'sw-grid.column.name' })).toBe('sw-grid.column.name');
            expect($te).toHaveBeenCalledTimes(1);
            expect($te).toHaveBeenCalledWith('sw-grid.column.name');
        });
    });
});
