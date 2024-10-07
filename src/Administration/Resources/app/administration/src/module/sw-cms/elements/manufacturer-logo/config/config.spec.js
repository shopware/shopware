/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultProps = {
    element: {
        config: {
            media: {
                source: 'static',
                value: null,
                required: true,
                entity: {
                    name: 'media',
                },
            },
            displayMode: {
                source: 'static',
                value: 'cover',
            },
            url: {
                source: 'static',
                value: null,
            },
            newTab: {
                source: 'static',
                value: true,
            },
            minHeight: {
                source: 'static',
                value: null,
            },
            verticalAlign: {
                source: 'static',
                value: null,
            },
            horizontalAlign: {
                source: 'static',
                value: null,
            },
        },
        data: {
            media: '',
        },
    },
    defaultConfig: {},
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-manufacturer-logo', {
        sync: true,
    }), {
        props: {
            ...defaultProps,
        },
        global: {
            stubs: {
                'sw-switch-field': true,
                'sw-select-field': {
                    template: '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                    props: ['value', 'options'],
                },
                'sw-text-field': true,
                'sw-cms-mapping-field': await wrapTestComponent('sw-cms-mapping-field'),
                'sw-media-upload-v2': true,
                'sw-upload-listener': true,
                'sw-dynamic-url-field': true,
                'sw-alert': true,
                'sw-media-modal-v2': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-icon': true,

            },
            provide: {
                repositoryFactory: {
                    create: () => {},
                },
                cmsService: Shopware.Service('cmsService'),
            },
        },
    });
}

describe('module/sw-cms/elements/manufacturer-logo/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/manufacturer-logo');

        Shopware.Store.get('cmsPage').setCurrentPage({
            type: 'product_detail',
        });
    });

    it('should map to a product manufacturer media if the component is in a product page', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.element.config.media.source).toBe('mapped');
        expect(wrapper.vm.element.config.media.value).toBe('product.manufacturer.media');
    });

    it('should not initially map to a product manufacturer media if element translated config', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    ...defaultProps.element.config,
                    media: {
                        source: 'static',
                        value: '1',
                        required: true,
                        entity: {
                            name: 'media',
                        },
                    },
                },
                data: {
                    media: {
                        url: 'http://shopware.com/image.jpg',
                        id: '1',
                    },
                },
                translated: {
                    config: {
                        media: {
                            source: 'static',
                            value: '1',
                        },
                    },
                },
            },
        });

        expect(wrapper.vm.element.config.media.source).toBe('static');
        expect(wrapper.vm.element.config.media.value).toBe('1');
    });
});
