/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-tabs', { sync: true }), {
        global: {
            stubs: {
                'sw-tabs-deprecated': true,
                'mt-tabs': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-tabs', () => {
    it('should render the deprecated tabs by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-tabs-deprecated');
        expect(wrapper.html()).not.toContain('mt-tabs');
    });

    it('should render the mt-tabs with an opt-in before the v6.8.0.0 feature flag is active', async () => {
        const wrapper = await createWrapper({
            props: {
                useMeteorComponent: true,
            },
        });

        expect(wrapper.html()).toContain('mt-tabs');
        expect(wrapper.html()).not.toContain('sw-tabs-deprecated');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render the deprecated tabs without an opt-in', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-tabs-deprecated');
        expect(wrapper.html()).not.toContain('mt-tabs');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render the mt-tabs with an opt-in', async () => {
        const wrapper = await createWrapper({
            props: {
                useMeteorComponent: true,
            },
        });

        expect(wrapper.html()).toContain('mt-tabs');
        expect(wrapper.html()).not.toContain('sw-tabs-deprecated');
    });
});
