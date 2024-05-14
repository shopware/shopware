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
                    'sw-switch-field': true,
                    'sw-checkbox-field': true,
                    'sw-button': true,
                    'sw-icon': true,
                    'sw-context-menu-divider': true,
                    'sw-button-group': true,
                },
            },
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should change value of compact based on prop', async () => {
        const switchButton = wrapper.findAll('sw-switch-field-stub');
        expect(switchButton.at(0).attributes().value).toBeTruthy();
    });

    it('should change value of previews based on prop', async () => {
        const switchButton = wrapper.findAll('sw-switch-field-stub');
        expect(switchButton.at(1).attributes().value).toBeUndefined();
    });

    it('should render a row for each item in column prop', async () => {
        const rows = wrapper.findAll('.sw-data-grid__settings-column-item');
        expect(rows).toHaveLength(2);
    });
});
