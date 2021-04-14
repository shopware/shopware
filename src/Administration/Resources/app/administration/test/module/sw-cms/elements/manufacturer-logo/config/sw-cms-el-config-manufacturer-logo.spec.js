import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/image/config';
import 'src/module/sw-cms/elements/manufacturer-logo/config';

function createWrapper(propsOverride) {
    return shallowMount(Shopware.Component.build('sw-cms-el-config-manufacturer-logo'), {
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
        stubs: {
            'sw-cms-mapping-field': true,
            'sw-media-upload-v2': true,
            'sw-upload-listener': true,
            'sw-text-field': true,
            'sw-select-field': true,
            'sw-field': true
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
            repositoryFactory: {
                create: () => {}
            },
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

describe('module/sw-cms/elements/manufacturer-logo/config', () => {
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

    it('should not initially map to a product manufacturer media if element translated config', () => {
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
                },
                translated: {
                    config: {
                        media: {
                            source: 'static',
                            value: '1'
                        }
                    }
                }
            }
        });

        expect(wrapper.vm.element.config.media.source).toBe('static');
        expect(wrapper.vm.element.config.media.value).toBe('1');
    });
});
