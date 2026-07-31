/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-card', { sync: true }), {
        global: {
            stubs: {
                'sw-card-deprecated': true,
                'mt-card': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-card', () => {
    // CHANGE REASON: The component always renders mt-card and no longer reads ENABLE_METEOR_COMPONENTS. @cleanup
    it('should render the mt-card', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-card');
    });
});
