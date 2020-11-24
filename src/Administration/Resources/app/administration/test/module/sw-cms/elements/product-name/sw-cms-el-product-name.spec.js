import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/text/component';
import 'src/module/sw-cms/elements/product-name/component';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-cms-el-product-name'), {
        propsData: {
            element: {
                config: {
                    content: {
                        source: 'static',
                        value: null
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null
                    }
                }
            },
            defaultConfig: {}
        },
        mocks: {
            $sanitize: key => key
        },
        data() {
            return {
                cmsPageState: {
                    currentPage: {
                        type: 'product_detail'
                    }
                }
            };
        },
        provide: {
            cmsService: {
                getPropertyByMappingPath: () => {}
            }
        },
        stubs: {
            'sw-text-editor': true
        }
    });
}

describe('module/sw-cms/elements/product-name/component', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should map to a product name if the component is in a product page', () => {
        expect(wrapper.vm.element.config.content.source).toBe('mapped');
        expect(wrapper.vm.element.config.content.value).toBe('product.name');
    });
});
