/**
 * @package admin
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';

jest.useFakeTimers();
jest.spyOn(global, 'setTimeout');

async function createWrapper() {
    return mount(await wrapTestComponent('sw-ai-copilot-badge', { sync: true }), {
        attachTo: document.body,
        global: {
            stubs: {
                'sw-icon': true,
            },
            directives: {
                tooltip: Shopware.Directive.getByName('tooltip'),
            },
        },
    });
}

describe('src/app/asyncComponent/feedback/sw-ai-copilot-badge/index.ts', () => {
    /* @type Wrapper */
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the label by default', () => {
        expect(wrapper.find('.sw-ai-copilot-badge__label').exists()).toBe(true);
    });

    it('should hide the label if the prop label is false', async () => {
        await wrapper.setProps({
            label: false,
        });

        expect(wrapper.find('.sw-ai-copilot-badge__label').exists()).toBe(false);
    });

    it('should not use the tooltip by default', async () => {
        const tooltipBaseElement = wrapper.find('[tooltip-id]');

        await tooltipBaseElement.trigger('mouseenter');

        jest.runAllTimers();

        const tooltip = document.body.querySelector('.sw-tooltip');
        expect(tooltip).toBeNull();
    });

    it('should use the tooltip if the prop label is false', async () => {
        await wrapper.setProps({
            label: false,
        });

        const tooltipBaseElement = wrapper.find('[tooltip-id]');

        await tooltipBaseElement.trigger('mouseenter');

        jest.runAllTimers();

        const tooltip = document.body.querySelector('.sw-tooltip');
        expect(tooltip).not.toBeNull();
    });
});
