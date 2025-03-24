/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-icon', { sync: true }), {
        props: {
            name: 'regular-circle-download',
        },
    });
}

describe('src/app/component/base/mt-icon/index.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the correct icon (circle-download)', async () => {
        expect(wrapper.find('.mt-icon').exists()).toBeTruthy();
        expect(wrapper.find('svg#meteor-icon-kit__regular-circle-download').exists()).toBeTruthy();
    });

    it('should render the correct icon (regular-fingerprint)', async () => {
        await wrapper.setProps({
            name: 'regular-fingerprint',
        });
        await flushPromises();

        expect(wrapper.find('.mt-icon').exists()).toBeTruthy();
        expect(wrapper.find('svg#meteor-icon-kit__regular-fingerprint').exists()).toBeTruthy();
    });

    it('should render the correct color', async () => {
        await wrapper.setProps({
            color: 'rgb(123, 0, 123)',
        });

        expect(wrapper.find('.mt-icon').attributes('style')).toContain('color: rgb(123, 0, 123);');

        await wrapper.setProps({
            color: 'rgb(255, 0, 42)',
        });

        expect(wrapper.find('.mt-icon').attributes('style')).toContain('color: rgb(255, 0, 42);');
    });

    it('should have aria hidden attribute when prop is set to decorative', async () => {
        expect(wrapper.find('.mt-icon').attributes('aria-hidden')).toBe('false');

        await wrapper.setProps({
            decorative: true,
        });

        expect(wrapper.find('.mt-icon').attributes('aria-hidden')).toBe('true');
    });
});
