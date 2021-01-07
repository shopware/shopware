import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/product-box/component';

const defaultElementConfig = {
    product: {
        source: 'static',
        value: null,
        required: true
    },
    boxLayout: {
        source: 'static',
        value: 'standard'
    },
    displayMode: {
        source: 'static',
        value: 'standard'
    },
    verticalAlign: {
        source: 'static',
        value: null
    }
};

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-cms-el-product-box'), {
        propsData: {
            element: {
                config: { ...defaultElementConfig }
            },
            defaultConfig: {
                displayMode: {
                    value: null
                },
                verticalAlign: {
                    value: null
                }
            }
        },
        mocks: {
            $tc: v => v
        },
        data() {
            return {
                cmsPageState: {
                    currentPage: {
                        type: 'landingpage'
                    }
                }
            };
        },
        provide: {
            feature: {
                isActive: () => true
            },
            cmsService: {
                getCmsElementRegistry: () => {
                    return { 'product-box': {
                        defaultData: {
                            boxLayout: 'standard',
                            product: null
                        }
                    } };
                }
            }
        }
    });
}

describe('module/sw-cms/elements/product-box/component', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should display skeleton when product data is null', () => {
        expect(wrapper.find('.sw-cms-el-product-box__skeleton-name').exists()).toBeTruthy();
    });

    it('should not display skeleton when product data is not null', async () => {
        await wrapper.setProps({
            element: {
                config: { ...defaultElementConfig },
                data: {
                    product: {
                        name: 'Lorem Ipsum dolor',
                        description: `Lorem ipsum dolor sit amet, consetetur sadipscing elitr,
                          sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat,
                          sed diam voluptua.`.trim(),
                        price: [
                            { gross: 19.90 }
                        ],
                        cover: {
                            media: {
                                url: '/administration/static/img/cms/preview_glasses_large.jpg',
                                alt: 'Lorem Ipsum dolor'
                            }
                        }
                    }
                }
            }
        });

        expect(wrapper.find('.sw-cms-el-product-box__skeleton-name').exists()).toBeFalsy();
    });
});
