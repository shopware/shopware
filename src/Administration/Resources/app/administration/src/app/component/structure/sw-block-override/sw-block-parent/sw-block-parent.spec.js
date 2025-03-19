/**
 * @sw-package framework
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-block-parent', { sync: true }));
}

describe('block-parent', () => {
    it('renders nothing if undefined is passed', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('*').exists()).toBeFalsy();
    });
});
