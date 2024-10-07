/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

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

async function createWrapper(additionalProps = {}) {
    return mount(await wrapTestComponent('sw-chart-card', { sync: true }), {
        props: {
            positionIdentifier: 'sw-chart-card__statistics-count',
            defaultRangeIndex,
            ...additionalProps,
        },
        global: {
            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot /><slot name="title"></slot></div>',
                    props: ['helpText'],
                },
                'sw-select-field': true,
                'sw-chart': true,
                'sw-icon': true,
            },
        },
    });
}

describe('src/app/component/base/sw-chart-card', () => {
    it('properly checks for slot usage', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.hasHeaderLink).toBeFalsy();
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
        await wrapper.setData({ selectedRange: expectedValue });

        wrapper.vm.dispatchRangeUpdate();

        expect(wrapper.emitted()).toHaveProperty(expectedEvent);
        expect(wrapper.emitted()[expectedEvent]).toHaveLength(1);
        expect(wrapper.emitted()[expectedEvent][0]).toHaveLength(1);
        expect(wrapper.emitted()[expectedEvent][0][0]).toBe(expectedValue);
    });

    // eslint-disable-next-line max-len
    it('should emit "sw-chart-card-range-update" with current value of selectedRange property with non-default availableRanges', async () => {
        const expectedEvent = 'sw-chart-card-range-update';
        const expectedRange = extendedRanges[2];

        const wrapper = await createWrapper({
            availableRanges: extendedRanges,
        });
        expect(wrapper.vm.selectedRange).toStrictEqual(defaultRange);

        await wrapper.setData({ selectedRange: expectedRange });
        wrapper.vm.dispatchRangeUpdate();

        expect(wrapper.emitted()).toHaveProperty(expectedEvent);
        expect(wrapper.emitted()[expectedEvent]).toHaveLength(1);
        expect(wrapper.emitted()[expectedEvent][0]).toHaveLength(1);
        expect(wrapper.emitted()[expectedEvent][0][0]).toStrictEqual(expectedRange);
        expect(wrapper.vm.selectedRange).toEqual(expectedRange);
    });

    it('should set the correct the position identifier from the prop to the card', async () => {
        const wrapper = await createWrapper();
        const swCard = wrapper.find('.sw-card');

        expect(swCard.attributes('position-identifier')).toBe('sw-chart-card__statistics-count');

        await wrapper.setProps({
            positionIdentifier: 'sw-dashboard-statistics__statistics-sum',
        });

        expect(swCard.attributes('position-identifier')).toBe('sw-dashboard-statistics__statistics-sum');
    });

    it('should show a help text to be accessible, when set', async () => {
        const expectedHelpText = 'Hello, I am help text';
        const wrapper = await createWrapper({ helpText: expectedHelpText });

        const swIcon = wrapper.find('.sw-chart-card__title-help-text');
        expect(swIcon.exists()).toBe(true);
        expect(wrapper.vm.helpText).toBe(expectedHelpText);
    });

    it('should not show a help text to be accessible, when not set', async () => {
        const wrapper = await createWrapper();

        const swIcon = wrapper.find('.sw-chart-card__title-help-text');
        expect(swIcon.exists()).toBeFalsy();
    });
});
