/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-help-center', { sync: true }), {
        global: {
            stubs: {
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-icon': true,
                'sw-popover': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'sw-external-link': true,
            },
        },
    });
}

describe('components/utils/sw-help-center', () => {
    let wrapper;

    it('should open the context menu when the button is clicked', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const button = wrapper.get('.sw-context-button');
        await button.trigger('click');
        await flushPromises();

        const contextMenu = wrapper.get('.sw-context-menu');

        expect(contextMenu.get('h3').text()).toBe('global.sw-help-center.title');
    });
});
