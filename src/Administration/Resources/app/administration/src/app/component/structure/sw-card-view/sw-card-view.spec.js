/**
 * @package admin
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-card-view', { sync: true }), {
        global: {
            stubs: {
                'sw-error-summary': true,
            },
        },
        props: {
            showErrorSummary: true,
        },
    });
}

describe('src/app/component/structure/sw-card-view', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be able to turn off the error summary component', async () => {
        expect(wrapper.find('sw-error-summary-stub').exists()).toBeTruthy();

        await wrapper.setProps({
            showErrorSummary: false,
        });

        expect(wrapper.find('sw-error-summary-stub').exists()).toBeFalsy();
    });
});
