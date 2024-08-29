/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-select-number-field', { sync: true }), {
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-icon': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
            },
        },
        props: {
            value: 0,
            options: [
                { name: 'Label #1', id: 1 },
                { name: 'Label #2', id: 2 },
                { name: 'Label #3', id: 3 },
                { name: 'Label #4', id: 4 },
                { name: 'Label #5', id: 5 },
            ],
        },
        ...additionalOptions,
    });
}

describe('src/app/component/form/sw-select-number-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should emit the value as a number', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const select = wrapper.find('select');
        await select.setValue('5');

        expect(wrapper.emitted()['update:value']).toBeTruthy();
        expect(wrapper.emitted()['update:value'][0]).toEqual([5]);
        expect(typeof wrapper.emitted()['update:value'][0][0]).toBe('number');
        expect(wrapper.vm.currentValue).toBe(5);
        expect(typeof wrapper.vm.currentValue).toBe('number');
    });
});
