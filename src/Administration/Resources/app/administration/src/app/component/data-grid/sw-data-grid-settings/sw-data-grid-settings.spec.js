/**
 * @package admin
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';
import { MtSwitch } from '@shopware-ag/meteor-component-library';

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
                    'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                    'sw-switch-field': await wrapTestComponent('sw-switch-field', { sync: true }),
                    'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                    'sw-checkbox-field': true,
                    'sw-button': true,
                    'sw-icon': true,
                    'sw-context-menu-divider': true,
                    'sw-button-group': true,
                    'mt-switch': MtSwitch,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
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
        expect(rows).toHaveLength(2);
    });
});
