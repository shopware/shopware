/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-button', { sync: true }), {
        global: {
            stubs: {
                'mt-button': true,
                'sw-button-deprecated': true,
            },
        },
    });
}

describe('components/base/sw-button', () => {
    // NOTE FOR REVIEWERS: sw-button ignores ENABLE_METEOR_COMPONENTS and always renders mt-button.
    it('should render the mt-button', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-button');
    });
});
