import { mount } from '@vue/test-utils_v3';

async function createWrapper({
    type,
    value,
    visibleValue,
}) {
    return mount(await wrapTestComponent('sw-condition-unit-menu', { sync: true }), {
        props: {
            type,
            value,
            visibleValue,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-icon': true,
                'sw-popover': true,
            },
        },
    });
}

describe('components/rule/sw-condition-unit-menu', () => {
    it('should not render conversion menu button when default unit is undefined', async () => {
        const wrapper = await createWrapper({
            type: 'age',
            value: 1,
            visibleValue: undefined,
        });

        expect(wrapper.find('.sw-condition-unit-menu__wrapper button').exists()).toBe(false);
        expect(wrapper.find('.sw-condition-unit-menu__wrapper .sw-condition-unit-menu__label').exists()).toBe(true);
    });

    it('should be possible to open the menu', async () => {
        const wrapper = await createWrapper({
            type: 'weight',
            value: 1,
            visibleValue: undefined,
        });

        // menu should be closed by default
        expect(wrapper.find('.sw-condition-unit-menu__menu').exists()).toBe(false);

        // open menu
        await wrapper.get('.sw-condition-unit-menu').trigger('click');
        expect(wrapper.get('.sw-condition-unit-menu__menu').exists()).toBe(true);

        // close menu
        await wrapper.get('.sw-condition-unit-menu').trigger('click');
        expect(wrapper.find('.sw-condition-unit-menu__menu').exists()).toBe(false);
    });

    it('should be possible to close the menu', async () => {
        const wrapper = await createWrapper({
            type: 'weight',
            value: 1,
            visibleValue: undefined,
        });

        // open menu
        await wrapper.find('.sw-condition-unit-menu').trigger('click');

        // close menu
        await wrapper.find('.sw-condition-unit-menu').trigger('click');
        expect(wrapper.find('.sw-condition-unit-menu__menu').exists()).toBe(false);
    });

    it('should close the menu when the user button loses focus', async () => {
        const wrapper = await createWrapper({
            type: 'weight',
            value: 1,
            visibleValue: undefined,
        });

        // open menu
        await wrapper.find('.sw-condition-unit-menu').trigger('click');
        expect(wrapper.find('.sw-condition-unit-menu__menu').exists()).toBe(true);

        // close menu
        await wrapper.find('.sw-condition-unit-menu').trigger('click');

        expect(wrapper.find('.sw-condition-unit-menu__menu').exists()).toBe(false);
    });

    it('should convert the base value to the selected value: kg -> g', async () => {
        const wrapper = await createWrapper({
            type: 'weight',
            value: 1,
            visibleValue: undefined,
        });

        // open menu
        await wrapper.find('.sw-condition-unit-menu').trigger('click');

        // convert to grams
        await wrapper.findAll('.sw-condition-unit-menu__menu-item').at(0).trigger('click');

        const changeUnitEvents = wrapper.emitted('change-unit');

        // should only be thrown once
        expect(changeUnitEvents).toHaveLength(1);
        expect(changeUnitEvents[0]).toStrictEqual([{
            unit: 'g',
            value: 1000,
        }]);
    });

    it('should convert the converted value back to the base value: g -> kg', async () => {
        const wrapper = await createWrapper({
            type: 'weight',
            value: 1,
            visibleValue: undefined,
        });

        // convert to grams
        await wrapper.find('.sw-condition-unit-menu').trigger('click');
        await wrapper.findAll('.sw-condition-unit-menu__menu-item').at(0).trigger('click');

        // convert back to kilograms
        await wrapper.find('.sw-condition-unit-menu').trigger('click');
        await wrapper.findAll('.sw-condition-unit-menu__menu-item').at(1).trigger('click');

        const changeUnitEvents = wrapper.emitted('change-unit');

        // should only be thrown once
        expect(changeUnitEvents).toHaveLength(2);
        expect(changeUnitEvents[1]).toStrictEqual([{
            unit: 'kg',
            value: 1,
        }]);
    });

    it('should render "weight" unit options', async () => {
        const wrapper = await createWrapper({
            type: 'weight',
            value: 1,
            visibleValue: undefined,
        });

        await wrapper.find('.sw-condition-unit-menu').trigger('click');
        expect(wrapper.findAll('.sw-condition-unit-menu__menu-item')).toHaveLength(4);
    });

    it('should render "dimension" unit options', async () => {
        const wrapper = await createWrapper({
            type: 'dimension',
            value: 1,
            visibleValue: undefined,
        });

        await wrapper.find('.sw-condition-unit-menu').trigger('click');
        expect(wrapper.findAll('.sw-condition-unit-menu__menu-item')).toHaveLength(7);
    });

    it('should render "time" unit options', async () => {
        const wrapper = await createWrapper({
            type: 'time',
            value: 1,
            visibleValue: undefined,
        });

        await wrapper.find('.sw-condition-unit-menu').trigger('click');
        expect(wrapper.findAll('.sw-condition-unit-menu__menu-item')).toHaveLength(6);
    });

    it('should render "volume" unit options', async () => {
        const wrapper = await createWrapper({
            type: 'volume',
            value: 1,
            visibleValue: undefined,
        });

        await wrapper.find('.sw-condition-unit-menu').trigger('click');
        expect(wrapper.findAll('.sw-condition-unit-menu__menu-item')).toHaveLength(5);
    });

    it('should have empty unit options when type is unknown', async () => {
        const wrapper = await createWrapper({
            type: 'age',
            value: 1,
            visibleValue: undefined,
        });

        expect(wrapper.vm.unitOptions).toEqual([]);
        expect(wrapper.vm.unitSnippet).toBe('global.sw-condition-generic.units.age');
    });
});
