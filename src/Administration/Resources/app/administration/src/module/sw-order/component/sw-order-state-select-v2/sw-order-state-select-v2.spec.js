import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */

describe('src/module/sw-order/component/sw-order-state-select-v2', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-order-state-select-v2', { sync: true }), {
            global: {
                stubs: {
                    'sw-single-select': await wrapTestComponent('sw-single-select', { sync: true }),
                    'sw-highlight-text': true,
                    'sw-select-result': true,
                    'sw-select-result-list': true,
                    'sw-select-base': true,
                },
            },
            props: {
                stateType: 'order',
            },
        });
    }

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should disabled single select if transition options props are empty', async () => {
        const wrapper = await createWrapper();
        const singleSelect = wrapper.findComponent('.sw-single-select');

        expect(singleSelect.attributes('disabled')).toBeTruthy();
    });

    it('should enable single select if transition options props has value', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            transitionOptions: [
                {
                    disabled: false,
                    id: 'do_pay',
                    name: 'In progress',
                    stateName: 'in_progress',
                },
            ],
        });

        const singleSelect = wrapper.findComponent('.sw-single-select');
        expect(singleSelect.attributes('disabled')).toBeUndefined();
    });

    it('should emit state-select event', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            transitionOptions: [
                {
                    disabled: false,
                    id: 'do_pay',
                    name: 'In progress',
                    stateName: 'in_progress',
                },
            ],
        });

        const singleSelect = wrapper.findComponent('.sw-single-select');
        await singleSelect.vm.$emit('update:value', 'in_progress');

        expect(wrapper.emitted('state-select')[0]).toEqual([
            'order',
            'in_progress',
        ]);
    });

    it('should show placeholder correctly', async () => {
        const wrapper = await createWrapper();
        const singleSelect = wrapper.findComponent('.sw-single-select');

        expect(singleSelect.props('placeholder')).toBe('sw-order.stateCard.labelSelectStatePlaceholder');

        await wrapper.setProps({
            placeholder: 'Open',
        });

        expect(singleSelect.props('placeholder')).toBe('Open');
    });
});
