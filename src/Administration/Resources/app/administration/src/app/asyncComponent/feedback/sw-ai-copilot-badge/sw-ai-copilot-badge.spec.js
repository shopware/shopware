import { shallowMount } from '@vue/test-utils';
import SwAiCopilotBadge from './index';

jest.useFakeTimers();
jest.spyOn(global, 'setTimeout');

Shopware.Component.register('sw-ai-copilot-badge', SwAiCopilotBadge);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-ai-copilot-badge'), {
        attachTo: document.body,
        stubs: {
            'sw-icon': true,
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

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }

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
