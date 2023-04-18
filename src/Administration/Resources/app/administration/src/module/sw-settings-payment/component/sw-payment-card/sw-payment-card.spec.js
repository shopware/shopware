import { shallowMount } from '@vue/test-utils';
import swPaymentCard from 'src/module/sw-settings-payment/component/sw-payment-card';

/**
 * @package checkout
 */

Shopware.Component.register('sw-payment-card', swPaymentCard);

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-payment-card'), {
        propsData: {
            paymentMethod: {
                id: '5e6f7g8h',
                translated: {
                    name: 'Test settings-payment 2',
                },
                active: true,
            },
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                },
            },
        },
        stubs: {
            'sw-card': true,
            'sw-internal-link': true,
            'sw-switch-field': true,
            'sw-media-preview-v2': true,
        },
    });
}

describe('module/sw-settings-payment/component/sw-payment-card', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to edit a payment method', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const editLink = wrapper.find('sw-internal-link-stub');
        expect(editLink.attributes().disabled).toBeTruthy();

        const activeToggle = wrapper.find('sw-switch-field-stub');
        expect(activeToggle.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a payment method', async () => {
        const wrapper = await createWrapper(['payment.editor']);
        await wrapper.vm.$nextTick();

        const editLink = wrapper.find('sw-internal-link-stub');
        expect(editLink.attributes().disabled).toBeFalsy();

        const activeToggle = wrapper.find('sw-switch-field-stub');
        expect(activeToggle.attributes().disabled).toBeFalsy();
    });
});

