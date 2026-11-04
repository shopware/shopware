/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const product = { id: 'product-1', name: 'Lorem ipsum' };

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-product-box', { sync: true }), {
        props: {
            element: {
                type: 'product-box',
                data: {},
            },
        },
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
                repositoryFactory: {
                    create: () => ({ get: () => Promise.resolve(product) }),
                },
            },
            stubs: {
                'sw-entity-single-select': {
                    template:
                        '<input class="sw-entity-single-select" @input="$emit(\'update:value\', $event.target.value || null)">',
                },
                'sw-product-variant-info': true,
                'sw-select-field': true,
                'sw-select-result': true,
                'sw-cms-inherit-wrapper': {
                    template: '<div><slot :isInherited="false"></slot></div>',
                    props: [
                        'field',
                        'element',
                        'contentEntity',
                        'label',
                    ],
                },
            },
        },
    });
}

describe('src/module/sw-cms/elements/product-box/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/product-box');
    });

    it('should assign the selected product to the element', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-entity-single-select').setValue('product-1');
        await flushPromises();

        const [[element]] = wrapper.emitted('element-update');
        expect(element.config.product.value).toBe('product-1');
        expect(element.data.product).toEqual(product);
    });

    it('should remove the product from the element when the selection is cleared', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-entity-single-select').setValue('');
        await flushPromises();

        const [[element]] = wrapper.emitted('element-update');
        expect(element.config.product.value).toBeNull();
        expect(element.data.product).toBeNull();
    });
});
