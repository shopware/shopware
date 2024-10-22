/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-rating-stars', { sync: true }), {
        props: {
            ...{
                value: 3.5,
            },
            ...props,
        },
        global: {
            stubs: {
                'sw-icon': true,
            },
        },
    });
}

const cases = {
    full: [
        { value: 2.0, renderPercentage: 0 },
        { value: 3.0, renderPercentage: 0 },
        { value: 7.0, renderPercentage: 0 },
        { value: 12.2, renderPercentage: 35 },
    ],
    partial: [
        { value: 1.3, renderPercentage: 35 },
        { value: 2.5, renderPercentage: 50 },
        { value: 1.4, renderPercentage: 50 },
        { value: 2.15, renderPercentage: 35 },
        { value: 1.8, renderPercentage: 65 },
    ],
};

describe('src/app/component/base/sw-rating-stars', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    const maxStarCases = [
        5,
        3,
        8,
    ];

    maxStarCases.forEach((maxStars) => {
        it(`should round render float values per default into full stars (MaxStars = ${maxStars})`, async () => {
            cases.full.forEach(async ({ value }) => {
                const wrapper = await createWrapper({ value, maxStars });

                const partialStar = wrapper.find('.star-partial');
                expect(partialStar.exists()).toBeFalsy();
            });
        });
    });

    maxStarCases.forEach((maxStars) => {
        it(`should round render float values per default into quarter stars (MaxStars = ${maxStars})`, async () => {
            cases.partial.forEach(async ({ value, renderPercentage }) => {
                const wrapper = await createWrapper({ value, maxStars });

                const partialStar = wrapper.find('.star-partial');
                expect(partialStar.attributes().style).toContain(`clip-path: inset(0 ${100 - renderPercentage}% 0 0);`);
            });
        });
    });
});
