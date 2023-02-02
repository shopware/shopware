import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-product-variant-info';

describe('components/base/sw-product-variant-info', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-product-variant-info'), {
            propsData: {
                variations: [{
                    group: 'Size',
                    option: 'M'
                }]
            },
            slots: {
                default: 'Product name from slot'
            }
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the main text from its slot', async () => {
        expect(wrapper.find('.sw-product-variant-info').text()).toContain('Product name from slot');
    });

    it('should display a specification', async () => {
        expect(wrapper.find('.sw-product-variant-info__specification').text()).toContain('Size: M');
    });

    afterEach(() => {
        wrapper.destroy();
    });
});
