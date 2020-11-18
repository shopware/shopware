import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-type-select';

function createWrapper(customProps = {}) {
    return shallowMount(Shopware.Component.build('sw-condition-type-select'), {
        stubs: {
            'sw-arrow-field': true,
            'sw-single-select': true
        },
        provide: {
            removeNodeFromTree: () => {}
        },
        mocks: {
            $tc: v => v
        },
        propsData: {
            condition: {},
            availableTypes: [],
            ...customProps
        }
    });
}

describe('src/app/component/rule/sw-condition-type-select', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled fields', async () => {
        const wrapper = createWrapper();

        const arrowField = wrapper.find('sw-arrow-field-stub');
        const singleSelect = wrapper.find('sw-single-select-stub');

        expect(arrowField.attributes().disabled).toBeUndefined();
        expect(singleSelect.attributes().disabled).toBeUndefined();
    });

    it('should have disabled fields', async () => {
        const wrapper = createWrapper();
        await wrapper.setProps({
            disabled: true
        });

        const arrowField = wrapper.find('sw-arrow-field-stub');
        const singleSelect = wrapper.find('sw-single-select-stub');

        expect(arrowField.attributes().disabled).toBe('true');
        expect(singleSelect.attributes().disabled).toBe('true');
    });
});
