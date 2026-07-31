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
    // CHANGE REASON: The component always renders mt-button and no longer reads ENABLE_METEOR_COMPONENTS. @cleanup
    it('should render the mt-button', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-button');
    });
});
