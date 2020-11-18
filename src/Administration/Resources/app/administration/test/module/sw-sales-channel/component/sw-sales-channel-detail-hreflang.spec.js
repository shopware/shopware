import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-detail-hreflang';

function createWrapper(customProps = {}) {
    return shallowMount(Shopware.Component.build('sw-sales-channel-detail-hreflang'), {
        stubs: {
            'sw-card': true,
            'sw-switch-field': true,
            'sw-entity-single-select': true
        },
        mocks: {
            $tc: v => v
        },
        propsData: {
            salesChannel: {
                hreflangActive: true
            },
            ...customProps
        }
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-detail-hreflang', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should enable the sw-switch-field', async () => {
        const wrapper = createWrapper();

        const switchField = wrapper.find('sw-switch-field-stub');

        expect(switchField.attributes().disabled).toBeUndefined();
    });

    it('should disable the sw-switch-field', async () => {
        const wrapper = createWrapper({
            disabled: true
        });

        const switchField = wrapper.find('sw-switch-field-stub');

        expect(switchField.attributes().disabled).toBe('true');
    });

    it('should enable the sw-entity-single-select', async () => {
        const wrapper = createWrapper();

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');

        expect(entitySingleSelect.attributes().disabled).toBeUndefined();
    });

    it('should disable the sw-entity-single-select', async () => {
        const wrapper = createWrapper({
            disabled: true
        });

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');

        expect(entitySingleSelect.attributes().disabled).toBe('true');
    });
});
