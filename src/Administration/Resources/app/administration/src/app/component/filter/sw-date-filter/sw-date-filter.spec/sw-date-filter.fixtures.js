/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

export async function createWrapper() {
    return mount(await wrapTestComponent('sw-date-filter', { sync: true }), {
        global: {
            stubs: {
                'sw-base-filter': await wrapTestComponent('sw-base-filter', {
                    sync: true,
                }),
                'sw-range-filter': await wrapTestComponent('sw-range-filter', {
                    sync: true,
                }),
                'sw-single-select': true,
                'mt-datepicker': {
                    props: ['modelValue'],
                    template: `
                    <div class="sw-field--datepicker">
                        <input type="text" ref="flatpickrInput" :value="modelValue" @input="onChange">
                    </div>`,
                    methods: {
                        onChange(e) {
                            this.$emit('update:modelValue', e.target.value);
                        },
                    },
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
            },
        },
        props: {
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
            },
            active: true,
        },
    });
}

/**
 * Registers the lifecycle hooks shared by every sw-date-filter spec:
 * fake timers anchored to 1337-12-31 and a UTC user before each test.
 */
export function setupDateFilterHooks() {
    beforeAll(() => {
        jest.useFakeTimers('modern');
        jest.setSystemTime(new Date(1337, 11, 31));
    });

    beforeEach(() => {
        Shopware.Store.get('session').setCurrentUser({ timeZone: 'UTC' });
    });

    afterAll(() => {
        jest.useRealTimers();
    });
}
