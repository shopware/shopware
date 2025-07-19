import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper(reason = 'status') {
    return mount(await wrapTestComponent('sw-order-save-changes-beforehand-modal', { sync: true }), {
        props: { reason },
        global: {
            stubs: {
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                        <slot></slot>
                        <slot name="modal-footer"></slot>
                        </div>
                    `,
                },
                'sw-button': {
                    template: '<button @click="$emit(\'click\')"><slot></slot></button>',
                },
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-save-changes-beforehand-modal', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should emit right event on button click', async () => {
        const wrapper = await createWrapper();

        const buttons = wrapper.findAll('button');
        expect(buttons).toHaveLength(2);

        await buttons[0].trigger('click');

        expect(wrapper.emitted('cancel')).toBeTruthy();
        expect(wrapper.emitted('confirm')).toBeFalsy();

        await buttons[1].trigger('click');
        expect(wrapper.emitted('confirm')).toBeTruthy();
    });
});
