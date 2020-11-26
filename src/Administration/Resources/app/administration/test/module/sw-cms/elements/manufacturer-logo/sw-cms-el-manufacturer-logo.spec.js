import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/image/component';
import 'src/module/sw-cms/elements/manufacturer-logo/component';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-cms-el-manufacturer-logo'), {
        propsData: {
            element: {
                config: {
                    media: {
                        source: 'static',
                        value: null,
                        required: true,
                        entity: {
                            name: 'media'
                        }
                    },
                    displayMode: {
                        source: 'static',
                        value: 'cover'
                    },
                    url: {
                        source: 'static',
                        value: null
                    },
                    newTab: {
                        source: 'static',
                        value: true
                    },
                    minHeight: {
                        source: 'static',
                        value: null
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null
                    }
                },
                data: {
                    media: ''
                }
            },
            defaultConfig: {}
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
                getCmsElementRegistry: () => {
                    return {};
                },
                getPropertyByMappingPath: () => {
                    return {};
                }
            }
        }
    });
}

describe('module/sw-cms/elements/manufacturer-logo/component', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should map to a product manufacturer media if the component is in a product page', () => {
        expect(wrapper.vm.element.config.media.source).toBe('mapped');
        expect(wrapper.vm.element.config.media.value).toBe('product.manufacturer.media');
    });
});
