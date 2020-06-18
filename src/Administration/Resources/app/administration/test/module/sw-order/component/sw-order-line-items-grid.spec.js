import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-line-items-grid';

function createWrapper({ privileges = [] }) {
    return shallowMount(Shopware.Component.build('sw-order-line-items-grid'), {
        propsData: {
            order: {
                price: {
                    taxStatus: ''
                }
            },
            context: {}
        },
        provide: {
            orderService: {},
            acl: {
                can: (key) => {
                    if (!key) return true;

                    return privileges.includes(key);
                }
            }
        },
        stubs: {
            'sw-container': true,
            'sw-button': true,
            'sw-button-group': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-data-grid': true
        },
        mocks: {
            $tc: t => t
        }
    });
}

describe('src/module/sw-order/component/sw-order-line-items-grid', () => {
    it('the create discounts button should not be disabled', () => {
        const wrapper = createWrapper({
            privileges: ['orders.create_discounts']
        });

        const button = wrapper.find('.sw-order-line-items-grid__can-create-discounts-button');
        expect(button.attributes()).not.toHaveProperty('disabled');
    });

    it('the create discounts button should not be disabled', () => {
        const wrapper = createWrapper({});

        const button = wrapper.find('.sw-order-line-items-grid__can-create-discounts-button');
        expect(button.attributes()).toHaveProperty('disabled');
    });
});
