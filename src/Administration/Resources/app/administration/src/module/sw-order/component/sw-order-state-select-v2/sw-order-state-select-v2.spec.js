import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const transitionOptions = [
    {
        disabled: true,
        id: 0,
        name: 'Open',
        stateName: 'open',
    },
    {
        disabled: false,
        id: 'do_pay',
        name: 'In progress',
        stateName: 'in_progress',
    },
];

describe('src/module/sw-order/component/sw-order-state-select-v2', () => {
    async function createWrapper(props = {}, slots = {}) {
        return mount(await wrapTestComponent('sw-order-state-select-v2', { sync: true }), {
            slots,
            global: {
                provide: {
                    stateStyleDataProviderService: {
                        getStyle: (stateMachine, stateName) => ({
                            meteorVariant: stateName === 'in_progress' ? 'info' : 'neutral',
                        }),
                    },
                },
            },
            props: {
                stateType: 'order',
                ...props,
            },
        });
    }

    it('should disable the select if transition options props are empty', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent('.mt-select').props('disabled')).toBe(true);
    });

    it('should enable the select if transition options props has value', async () => {
        const wrapper = await createWrapper({ transitionOptions });

        expect(wrapper.findComponent('.mt-select').props('disabled')).toBe(false);
    });

    it('should emit state-select event', async () => {
        const wrapper = await createWrapper({ transitionOptions, stateName: 'open' });

        await wrapper.findComponent('.mt-select').vm.$emit('update:modelValue', 'do_pay');

        expect(wrapper.emitted('state-select')).toEqual([['order', 'do_pay']]);
    });

    it('should not emit state-select event when the current state is selected', async () => {
        const wrapper = await createWrapper({ transitionOptions, stateName: 'open' });

        await wrapper.findComponent('.mt-select').vm.$emit('update:modelValue', 0);

        expect(wrapper.emitted('state-select')).toBeUndefined();
    });

    it('should show the current state as selected value', async () => {
        const wrapper = await createWrapper({ transitionOptions, stateName: 'open' });

        expect(wrapper.findComponent('.mt-select').props('modelValue')).toBe(0);
    });

    it('should show placeholder correctly', async () => {
        const wrapper = await createWrapper();
        const select = wrapper.findComponent('.mt-select');

        expect(select.props('placeholder')).toBe('sw-order.stateCard.labelSelectStatePlaceholder');

        await wrapper.setProps({
            placeholder: 'Open',
        });

        expect(select.props('placeholder')).toBe('Open');
    });

    it('should not render a clear button, because a state cannot be unset', async () => {
        const wrapper = await createWrapper({ transitionOptions });

        expect(wrapper.findComponent('.mt-select').props('hideClearableButton')).toBe(true);
    });

    it('should show a status dot for the current state in the field', async () => {
        const wrapper = await createWrapper({ transitionOptions, stateName: 'in_progress' });

        expect(wrapper.findComponent('.sw-order-state-select-v2__status-dot').props('variant')).toBe('info');
    });

    it('should not show a status dot in the field without a current state', async () => {
        const wrapper = await createWrapper({ transitionOptions });

        expect(wrapper.find('.sw-order-state-select-v2__status-dot').exists()).toBe(false);
    });

    it('should show status dots in the options with a current state', async () => {
        const wrapper = await createWrapper({ transitionOptions, stateName: 'open' });

        await wrapper.find('.mt-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-order-state-select-v2__state .mt-status-dot')).toHaveLength(2);
    });

    it('should not show status dots in the options without a current state', async () => {
        const wrapper = await createWrapper({ transitionOptions });

        await wrapper.find('.mt-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.find('.mt-select-option--0').text()).toBe('Open');
        expect(wrapper.find('.sw-order-state-select-v2__state').exists()).toBe(false);
    });

    it('should mark the select without a current state, so the placeholder reads as the current state', async () => {
        const wrapper = await createWrapper({ transitionOptions });

        expect(wrapper.classes()).toContain('sw-order-state-select-v2--without-state');

        await wrapper.setProps({ stateName: 'open' });

        expect(wrapper.classes()).not.toContain('sw-order-state-select-v2--without-state');
    });

    it('should render a passed hint below the field', async () => {
        const wrapper = await createWrapper({}, { hint: 'Status set 5 minutes ago by admin' });

        expect(wrapper.find('.mt-field-hint').text()).toBe('Status set 5 minutes ago by admin');
    });

    it('should not render a hint without a passed hint', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.mt-field-hint').exists()).toBe(false);
    });

    it.each([
        ['open', 'neutral'],
        ['in_progress', 'info'],
    ])('should resolve the status dot variant for state "%s"', async (stateName, variant) => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.getStateVariant(stateName)).toBe(variant);
    });
});
