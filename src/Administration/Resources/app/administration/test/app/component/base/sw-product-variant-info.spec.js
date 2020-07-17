import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-product-variant-info';

describe('components/base/sw-product-variant-info', () => {
    let wrapper;
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-product-variant-info'), {
            localVue,
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

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should display the main text from its slot', () => {
        expect(wrapper.find('.sw-product-variant-info').text()).toContain('Product name from slot');
    });

    it('should display a specification', () => {
        console.log('wrapper', wrapper.html());
        expect(wrapper.find('.sw-product-variant-info__specification').text()).toContain('Size: M');
    });

    afterEach(() => {
        wrapper.destroy();
    });
});
