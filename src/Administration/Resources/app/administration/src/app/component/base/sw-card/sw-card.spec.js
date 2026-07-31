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
    // NOTE FOR REVIEWERS: sw-card ignores ENABLE_METEOR_COMPONENTS and always renders mt-card.
    it('should render the mt-card', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-card');
    });
});
