/**
 * @sw-package framework
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

import SwJestBlockStateFixture from '../_mocks_/sw-jest-block-state-fixture.vue';
import SwJestBlockStateFixtureOverride from '../_mocks_/sw-jest-block-state-fixture.override.vue';

/**
 * Mounts the override, the way `sw-admin` mounts every registered one at boot, then the component it
 * extends. Both happen per test because the suite unmounts every wrapper after each one, and an
 * unmounted override takes its block registration with it.
 */
async function mountFixture() {
    const components = {
        'sw-block': await wrapTestComponent('sw-block', { sync: true }),
        'sw-block-parent': await wrapTestComponent('sw-block-parent', { sync: true }),
    };

    mount(SwJestBlockStateFixtureOverride, { global: { components } });

    return mount(SwJestBlockStateFixture, { global: { components } });
}

describe('override state inside <sw-block extends> content', () => {
    it('renders the override content next to the default content', async () => {
        const wrapper = await mountFixture();

        expect(wrapper.get('.default-content').text()).toBe('default');
        expect(wrapper.get('.counter').text()).toBe('0');
    });

    it('evaluates an expression on the value rather than on a ref object', async () => {
        const wrapper = await mountFixture();

        expect(wrapper.get('.doubled').text()).toBe('0');
    });

    it('mutates the override state from a template write', async () => {
        const wrapper = await mountFixture();

        await wrapper.get('.increment').trigger('click');

        expect(wrapper.get('.counter').text()).toBe('1');
        expect(wrapper.get('.doubled').text()).toBe('2');
    });
});
