/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultElementConfig = {
    product: null,
    boxLayout: {
        source: 'static',
        value: 'standard',
    },
    displayMode: {
        source: 'static',
        value: 'standard',
    },
    verticalAlign: {
        source: 'static',
        value: null,
    },
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-product-box', {
            sync: true,
        }),
        {
            props: {
                element: {
                    config: { ...defaultElementConfig },
                },
                defaultConfig: {
                    displayMode: {
                        value: null,
                    },
                    verticalAlign: {
                        value: null,
                    },
                },
            },
            global: {
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                },
                stubs: {},
            },
        },
    );
}

describe('module/sw-cms/elements/product-box/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/product-box');
    });

    it('should display skeleton when product data is null', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                config: { ...defaultElementConfig },
                data: {
                    product: null,
                },
            },
        });

        expect(wrapper.find('.sw-cms-el-product-box__skeleton-name').exists()).toBe(true);
    });

    it('should not display skeleton when product data is not null', async () => {
        const wrapper = await createWrapper();

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
                            { gross: 19.9 },
                        ],
                        cover: {
                            media: {
                                url: '/administration/static/img/cms/preview_glasses_large.jpg',
                                alt: 'Lorem Ipsum dolor',
                            },
                        },
                    },
                },
            },
        });

        expect(wrapper.find('.sw-cms-el-product-box__skeleton-name').exists()).toBe(false);
    });
});
