/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';

const defaultProps = {
    value: null,
    dateType: 'date',
    disabled: false,
};

const MtDatepickerStub = {
    name: 'MtDatepicker',
    inheritAttrs: false,
    props: [
        'modelValue',
        'disabled',
    ],
    emits: ['update:modelValue'],
    template: `<input
        :value="modelValue"
        :disabled="disabled"
        :class="$attrs.class"
        @input="$emit('update:modelValue', $event.target.value)"
    />`,
};

async function createWrapper(props = defaultProps) {
    return mount(await wrapTestComponent('sw-condition-value-between-date', { sync: true }), {
        props,
        global: {
            stubs: {
                'mt-datepicker': MtDatepickerStub,
            },
        },
    });
}

describe('src/components/rule/sw-condition-value-between-date', () => {
    it.each([
        { dateType: 'date', valid: true },
        { dateType: 'datetime', valid: true },
        { dateType: null, valid: true },
        { dateType: 'something', valid: false },
    ])('should validate date type: $dateType', async ({ dateType, valid }) => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        await createWrapper({
            ...defaultProps,
            dateType,
        });

        expect(warn).toHaveBeenCalledTimes(valid ? 0 : 1);

        warn.mockRestore();
    });

    it.each([
        {
            name: 'both set',
            value: { from: '2011/01/01', to: '2022/02/02' },
            expected: { from: '2011/01/01', to: '2022/02/02' },
        },
        { name: 'from only', value: { from: '2011/01/01' }, expected: { from: '2011/01/01', to: '' } },
        { name: 'null value', value: null, expected: { from: '', to: '' } },
    ])('should apply default values to datepicker: $name', async ({ value, expected }) => {
        const wrapper = await createWrapper({
            ...defaultProps,
            value,
        });

        expect(wrapper.find('.sw-condition-value-between-date__from').element.value).toBe(expected.from);
        expect(wrapper.find('.sw-condition-value-between-date__to').element.value).toBe(expected.to);
    });

    it('should emit from and to date values', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-condition-value-between-date__from').setValue('2011/01/01');
        await wrapper.find('.sw-condition-value-between-date__to').setValue('2022/02/02');

        expect(wrapper.emitted('update:value')).toEqual([
            [{ from: '2011/01/01', to: null }],
            [{ from: null, to: '2022/02/02' }],
        ]);
    });

    it.each([
        { name: 'enabled', disabled: false, expected: false },
        { name: 'disabled', disabled: true, expected: true },
    ])('should apply disabled state to datepickers: $name', async ({ disabled, expected }) => {
        const wrapper = await createWrapper({
            ...defaultProps,
            disabled,
        });

        expect(wrapper.find('.sw-condition-value-between-date__from').element.disabled).toBe(expected);
        expect(wrapper.find('.sw-condition-value-between-date__to').element.disabled).toBe(expected);
    });
});
