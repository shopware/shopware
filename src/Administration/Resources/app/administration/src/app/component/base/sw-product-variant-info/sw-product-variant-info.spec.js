/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-product-variant-info';

describe('components/base/sw-product-variant-info', () => {
    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-product-variant-info'), {
            propsData: {
                variations: [{
                    group: 'Size',
                    option: 'M',
                }],
            },
            slots: {
                default: 'Product name from slot',
            },
        });
    }

    it('should display the main text from its slot', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.find('.sw-product-variant-info').text()).toContain('Product name from slot');
    });

    it('should display a specification', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.find('.sw-product-variant-info__specification').text()).toContain('Size: M');
    });
});
