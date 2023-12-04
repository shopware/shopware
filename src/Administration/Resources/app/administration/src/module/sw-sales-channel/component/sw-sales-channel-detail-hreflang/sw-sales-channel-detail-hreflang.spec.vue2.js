/**
 * @package buyers-experience
 */

import { shallowMount } from '@vue/test-utils_v2';
import swSalesChannelDetailHreflang from 'src/module/sw-sales-channel/component/sw-sales-channel-detail-hreflang';

Shopware.Component.register('sw-sales-channel-detail-hreflang', swSalesChannelDetailHreflang);

async function createWrapper(customProps = {}) {
    return shallowMount(await Shopware.Component.build('sw-sales-channel-detail-hreflang'), {
        stubs: {
            'sw-card': true,
            'sw-switch-field': true,
            'sw-entity-single-select': true,
        },
        propsData: {
            salesChannel: {
                hreflangActive: true,
            },
            ...customProps,
        },
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-detail-hreflang', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should enable the sw-switch-field', async () => {
        const wrapper = await createWrapper();

        const switchField = wrapper.find('sw-switch-field-stub');

        expect(switchField.attributes().disabled).toBeUndefined();
    });

    it('should disable the sw-switch-field', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const switchField = wrapper.find('sw-switch-field-stub');

        expect(switchField.attributes().disabled).toBe('true');
    });

    it('should enable the sw-entity-single-select', async () => {
        const wrapper = await createWrapper();

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');

        expect(entitySingleSelect.attributes().disabled).toBeUndefined();
    });

    it('should disable the sw-entity-single-select', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');

        expect(entitySingleSelect.attributes().disabled).toBe('true');
    });
});
