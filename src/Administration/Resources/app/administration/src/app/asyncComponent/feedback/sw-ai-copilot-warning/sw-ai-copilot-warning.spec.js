import { shallowMount } from '@vue/test-utils';
import SwAiCopilotWarning from './index';

Shopware.Component.register('sw-ai-copilot-warning', SwAiCopilotWarning);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-ai-copilot-warning'), {
        stubs: {
            'sw-icon': true,
        },
    });
}

describe('src/app/asyncComponent/feedback/sw-ai-copilot-warning/index.ts', () => {
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

    it('should render the default text', () => {
        expect(wrapper.find('.sw-ai-copilot-warning').text()).toBe('sw-ai-copilot-warning.text');
    });

    it('should render correct icon', async () => {
        expect(wrapper.find('sw-icon-stub').attributes().name).toBe('solid-exclamation-triangle');
    });

    it('should be able to override the default text with custom text', async () => {
        await wrapper.setProps({
            text: 'Custom text',
        });

        // Ensure custom text is rendered instead of default text
        expect(wrapper.find('.sw-ai-copilot-warning').text()).toBe('Custom text');
    });
});
