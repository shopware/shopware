import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-visibility';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-bulk-edit-product-visibility'), {
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
                }
            }
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

    beforeEach(() => {
        console.error = jest.fn();
        wrapper = createWrapper();
    });

    afterEach(() => {
        console.error = consoleError;
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be disabled correctly', async () => {
        await wrapper.setProps({ disabled: true });
        expect(wrapper.find('sw-product-visibility-select-stub').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-card__quick-link.advanced-visibility').classes()).toContain('is--disabled');

        await wrapper.setProps({ disabled: false });
        expect(wrapper.find('sw-product-visibility-select-stub').attributes().disabled).toBe(undefined);
        expect(wrapper.find('.sw-card__quick-link.advanced-visibility').classes()).not.toContain('is--disabled');
    });
});
