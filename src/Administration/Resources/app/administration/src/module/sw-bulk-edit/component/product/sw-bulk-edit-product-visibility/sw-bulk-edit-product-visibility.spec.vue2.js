/**
 * @package system-settings
 */
import { shallowMount } from '@vue/test-utils_v2';
import swBulkEditProductVisibility from 'src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-visibility';

Shopware.Component.register('sw-bulk-edit-product-visibility', swBulkEditProductVisibility);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-bulk-edit-product-visibility'), {
        stubs: {
            'sw-inherit-wrapper': {
                template: '<div class="sw-inherit-wrapper"><slot name="content"></slot></div>',
            },
            'sw-product-visibility-select': true,
            'sw-container': true,
            'sw-icon': true,
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        create: () => {
                            return Promise.resolve();
                        },
                    };
                },
            },
        },
        propsData: {
            bulkEditProduct: {},
            disabled: false,
        },
    });
}

describe('sw-bulk-edit-product-visibility', () => {
    let wrapper;
    const consoleError = console.error;

    beforeAll(() => {
        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            state: () => {
                return {
                    product: {
                        visibilities: [{
                            productId: 'productId',
                            productVersionId: 'productVersionId',
                            salesChannel: {},
                            salesChannelId: 'salesChannelId',
                            visibility: 30,
                        }],
                    },
                };
            },
        });
    });

    beforeEach(async () => {
        console.error = jest.fn();
        wrapper = await createWrapper();
    });

    afterEach(() => {
        console.error = consoleError;
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be disabled correctly', async () => {
        await wrapper.setProps({ disabled: true });
        expect(wrapper.find('sw-product-visibility-select-stub').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-card__quick-link.advanced-visibility').classes()).toContain('is--disabled');

        await wrapper.setProps({ disabled: false });
        expect(wrapper.find('sw-product-visibility-select-stub').attributes().disabled).toBeUndefined();
        expect(wrapper.find('.sw-card__quick-link.advanced-visibility').classes()).not.toContain('is--disabled');
    });
});
