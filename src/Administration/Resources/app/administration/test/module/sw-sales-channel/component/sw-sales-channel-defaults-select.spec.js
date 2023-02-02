import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-defaults-select';

function createWrapper(customProps = {}) {
    const salesChannel = {};
    salesChannel.getEntityName = () => '';

    return shallowMount(Shopware.Component.build('sw-sales-channel-defaults-select'), {
        stubs: {
            'sw-container': true,
            'sw-entity-multi-select': true,
            'sw-entity-single-select': true
        },
        propsData: {
            salesChannel: salesChannel,
            propertyName: '',
            propertyLabel: '',
            defaultPropertyName: '',
            defaultPropertyLabel: '',
            ...customProps
        }
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-defaults-select', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have selects enabled', async () => {
        const wrapper = createWrapper();

        const multiSelect = wrapper.find('sw-entity-multi-select-stub');
        const singleSelect = wrapper.find('sw-entity-single-select-stub');

        expect(multiSelect.attributes().disabled).toBeUndefined();
        expect(singleSelect.attributes().disabled).toBeUndefined();
    });

    it('should have selects disabled', async () => {
        const wrapper = createWrapper({
            disabled: true
        });

        const multiSelect = wrapper.find('sw-entity-multi-select-stub');
        const singleSelect = wrapper.find('sw-entity-single-select-stub');

        expect(multiSelect.attributes().disabled).toBe('true');
        expect(singleSelect.attributes().disabled).toBe('true');
    });
});
