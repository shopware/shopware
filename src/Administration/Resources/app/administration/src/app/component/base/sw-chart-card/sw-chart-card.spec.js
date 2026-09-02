/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { h, nextTick } from 'vue';

const extendedRanges = [
    {
        label: '90Days',
        range: 90,
        interval: 'day',
        aggregate: 'day',
    },
    {
        label: '30Days',
        range: 30,
        interval: 'day',
        aggregate: 'day',
    },
    {
        label: '14Days',
        range: 14,
        interval: 'day',
        aggregate: 'day',
    },
    {
        label: '7Days',
        range: 7,
        interval: 'day',
        aggregate: 'day',
    },
];
const defaultRangeIndex = 1;
const defaultRange = extendedRanges[defaultRangeIndex];

async function createWrapper(additionalProps = {}, additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-chart-card', { sync: true }), {
        props: {
            positionIdentifier: 'sw-chart-card__statistics-count',
            defaultRangeIndex,
            ...additionalProps,
        },
        ...additionalOptions,
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card" :is-loading="isLoading"><slot /><slot name="title"></slot><slot name="headerRight"></slot></div>',
                    props: [
                        'helpText',
                        'isLoading',
                    ],
                },
                'mt-select': {
                    name: 'mt-select',
                    props: [
                        'modelValue',
                        'options',
                    ],
                    template: `
                        <div class="mt-select">
                            <div
                                v-for="(option, index) in options"
                                :key="index"
                                class="mt-select-option"
                            >
                                <slot
                                    name="result-label-property"
                                    v-bind="{ item: option, index }"
                                />
                            </div>
                        </div>
                    `,
                },
                'sw-chart': true,
            },
        },
    });
}

describe('src/app/component/base/sw-chart-card', () => {
    it('properly checks for slot usage', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.hasHeaderLink).toBeFalsy();
    });

    it('maps the legacy range-option slot to mt-select option labels', async () => {
        const wrapper = await createWrapper(
            {
                availableRanges: [
                    '30Days',
                    '14Days',
                    '7Days',
                    '24Hours',
                    'yesterday',
                ],
            },
            {
                slots: {
                    'range-option': ({ range }) => h('span', `Range ${range}`),
                },
            },
        );

        expect(wrapper.getComponent({ name: 'mt-select' }).props('options')).toEqual([
            { value: '30Days', label: 'Range 30Days' },
            { value: '14Days', label: 'Range 14Days' },
            { value: '7Days', label: 'Range 7Days' },
            { value: '24Hours', label: 'Range 24Hours' },
            { value: 'yesterday', label: 'Range yesterday' },
        ]);
    });

    it('forwards the option value to the legacy range-option slot', async () => {
        const wrapper = await createWrapper(
            {
                availableRanges: [
                    '30Days',
                    '14Days',
                ],
            },
            {
                slots: {
                    'range-option': ({ range }) => h('span', `Legacy ${range}`),
                },
            },
        );

        expect(wrapper.findAll('.mt-select-option').map((option) => option.text())).toEqual([
            'Legacy 30Days',
            'Legacy 14Days',
        ]);
    });

    it('falls back to the range as option label when the legacy slot is not used', async () => {
        const wrapper = await createWrapper({
            availableRanges: [
                '30Days',
                '14Days',
                '7Days',
                '24Hours',
                'yesterday',
            ],
        });

        expect(wrapper.getComponent({ name: 'mt-select' }).props('options')).toEqual(
            [
                '30Days',
                '14Days',
                '7Days',
                '24Hours',
                'yesterday',
            ].map((range) => ({ value: range, label: range })),
        );
    });

    it('updates the selected range through mt-select', async () => {
        const wrapper = await createWrapper();
        const select = wrapper.getComponent({ name: 'mt-select' });

        await select.vm.$emit('update:model-value', '7Days');

        expect(wrapper.vm.selectedRange).toBe('7Days');
        expect(wrapper.emitted('sw-chart-card-range-update')).toEqual([['7Days']]);
    });

    it('should set the correct range in the dropdown by default', async () => {
        const wrapper = await createWrapper({
            availableRanges: extendedRanges,
        });

        expect(wrapper.vm.selectedRange).toStrictEqual(defaultRange);
    });

    it('should emit "sw-chart-card-range-update" with current value of selectedRange property', async () => {
        const expectedEvent = 'sw-chart-card-range-update';
        const expectedValue = '7Days';
        const wrapper = await createWrapper();
        wrapper.vm.selectedRange = expectedValue;
        await nextTick();

        wrapper.vm.dispatchRangeUpdate();

        expect(wrapper.emitted()).toHaveProperty(expectedEvent);
        expect(wrapper.emitted()[expectedEvent]).toHaveLength(1);
        expect(wrapper.emitted()[expectedEvent][0]).toHaveLength(1);
        expect(wrapper.emitted()[expectedEvent][0][0]).toBe(expectedValue);
    });

    it('should emit "sw-chart-card-range-update" with current value of selectedRange property with non-default availableRanges', async () => {
        const expectedEvent = 'sw-chart-card-range-update';
        const expectedRange = extendedRanges[2];

        const wrapper = await createWrapper({
            availableRanges: extendedRanges,
        });
        expect(wrapper.vm.selectedRange).toStrictEqual(defaultRange);

        wrapper.vm.selectedRange = expectedRange;
        await nextTick();
        wrapper.vm.dispatchRangeUpdate();

        expect(wrapper.emitted()).toHaveProperty(expectedEvent);
        expect(wrapper.emitted()[expectedEvent]).toHaveLength(1);
        expect(wrapper.emitted()[expectedEvent][0]).toHaveLength(1);
        expect(wrapper.emitted()[expectedEvent][0][0]).toStrictEqual(expectedRange);
        expect(wrapper.vm.selectedRange).toEqual(expectedRange);
    });

    it('should set the correct the position identifier from the prop to the card', async () => {
        const wrapper = await createWrapper();
        const swCard = wrapper.find('.mt-card');

        expect(swCard.attributes('position-identifier')).toBe('sw-chart-card__statistics-count');

        await wrapper.setProps({
            positionIdentifier: 'sw-dashboard-statistics__statistics-sum',
        });

        expect(swCard.attributes('position-identifier')).toBe('sw-dashboard-statistics__statistics-sum');
    });

    it('should pass the loading state to the card', async () => {
        const wrapper = await createWrapper({
            isLoading: true,
        });

        expect(wrapper.find('.mt-card').attributes('is-loading')).toBe('true');
    });

    it('should show a help text to be accessible, when set', async () => {
        const expectedHelpText = 'Hello, I am help text';
        const wrapper = await createWrapper({ helpText: expectedHelpText });

        const icon = wrapper.find('.sw-chart-card__title-help-text');
        expect(icon.exists()).toBe(true);
        expect(wrapper.vm.helpText).toBe(expectedHelpText);
    });

    it('should not show a help text to be accessible, when not set', async () => {
        const wrapper = await createWrapper();

        const icon = wrapper.find('.sw-chart-card__title-help-text');
        expect(icon.exists()).toBeFalsy();
    });
});
