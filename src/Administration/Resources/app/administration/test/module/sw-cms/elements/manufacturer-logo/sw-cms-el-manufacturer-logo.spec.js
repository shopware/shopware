import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/image/component';
import 'src/module/sw-cms/elements/manufacturer-logo/component';

function createWrapper(propsOverride) {
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
            defaultConfig: {},
            ...propsOverride
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

    it('should not initially map to a product manufacturer media if element data media has value', () => {
        wrapper = createWrapper({
            element: {
                config: {
                    ...wrapper.props().element.config,
                    media: {
                        source: 'static',
                        value: '1',
                        required: true,
                        entity: {
                            name: 'media'
                        }
                    }
                },
                data: {
                    media: {
                        url: 'http://shopware.com/image.jpg',
                        id: '1'
                    }
                }
            }
        });

        expect(wrapper.vm.element.config.media.source).toBe('static');
        expect(wrapper.vm.element.config.media.value).toBe('1');
    });

    it('should update style regarding to config value', async () => {
        expect(wrapper.vm.styles).toEqual({
            'max-width': '180px',
            'min-height': '40px',
            'align-self': null
        });

        await wrapper.setProps({
            element: {
                config: {
                    ...wrapper.props().element.config,
                    displayMode: {
                        source: 'statics',
                        value: 'cover'
                    },
                    minHeight: {
                        source: 'static',
                        value: '50px'
                    }
                },
                data: {}
            }
        });

        expect(wrapper.vm.styles).toEqual({
            'max-width': '180px',
            'min-height': '50px',
            'align-self': null
        });

        await wrapper.setProps({
            element: {
                config: {
                    ...wrapper.props().element.config,
                    displayMode: {
                        source: 'statics',
                        value: 'standard'
                    },
                    verticalAlign: {
                        source: 'static',
                        value: 'center'
                    }
                },
                data: {}
            }
        });

        expect(wrapper.vm.styles).toEqual({
            'max-width': '180px',
            'min-height': '40px',
            'align-self': 'center'
        });
    });
});
