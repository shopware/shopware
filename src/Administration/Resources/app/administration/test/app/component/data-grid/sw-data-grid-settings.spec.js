import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/data-grid/sw-data-grid-settings';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/form/field-base/sw-base-field';

describe('components/data-grid/sw-data-grid-settings', () => {
    let wrapper;
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-data-grid-settings'), {
            localVue,
            stubs: {
                'sw-context-button': true,
                'sw-switch-field': true,
                'sw-checkbox-field': true,
                'sw-button': true,
                'sw-icon': true,
                'sw-context-menu-divider': true,
                'sw-button-group': true
            },
            propsData: {
                columns: [
                    { property: 'name', label: 'Name' },
                    { property: 'company', label: 'Company' }
                ],
                compact: true,
                previews: false,
                enablePreviews: true,
                disabled: false
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
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
        expect(rows.length).toBe(2);
    });
});
