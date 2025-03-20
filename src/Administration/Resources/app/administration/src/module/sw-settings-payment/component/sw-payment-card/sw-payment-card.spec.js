import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-payment-card', {
            sync: true,
        }),
        {
            props: {
                paymentMethod: {
                    id: '5e6f7g8h',
                    translated: {
                        name: 'Test settings-payment 2',
                    },
                    active: true,
                },
            },
            global: {
                renderStubDefaultSlot: true,
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
                    'sw-internal-link': true,

                    'sw-media-preview-v2': true,
                },
            },
        },
    );
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

        const activeToggle = wrapper.findComponent('.mt-switch');
        expect(activeToggle.props().disabled).toBe(true);
    });

    it('should be able to edit a payment method', async () => {
        const wrapper = await createWrapper(['payment.editor']);
        await wrapper.vm.$nextTick();

        const editLink = wrapper.find('sw-internal-link-stub');
        expect(editLink.attributes().disabled).toBeFalsy();

        const activeToggle = wrapper.find('.mt-switch');
        expect(activeToggle.attributes().disabled).toBeFalsy();
    });

    it('should correctly emit set-payment-active event', async () => {
        const wrapper = await createWrapper(['payment.editor']);
        await wrapper.vm.$nextTick();

        const activeToggle = wrapper.findComponent('.mt-switch');
        await activeToggle.vm.$emit('change', false);

        const expectedPaymentMethod = {
            id: '5e6f7g8h',
            translated: {
                name: 'Test settings-payment 2',
            },
            active: false,
        };

        expect(wrapper.emitted('set-payment-active')).toHaveLength(1);
        expect(wrapper.emitted('set-payment-active')[0]).toEqual([expectedPaymentMethod]);

        await activeToggle.vm.$emit('change', false);

        expect(wrapper.emitted('set-payment-active')).toHaveLength(1);
    });
});
