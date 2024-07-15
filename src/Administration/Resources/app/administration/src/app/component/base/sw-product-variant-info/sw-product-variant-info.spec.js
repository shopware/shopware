/**
 * @package admin
 * group disabledCompat
 */

import { mount } from '@vue/test-utils';

describe('components/base/sw-product-variant-info', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-product-variant-info', { sync: true }), {
            props: {
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
