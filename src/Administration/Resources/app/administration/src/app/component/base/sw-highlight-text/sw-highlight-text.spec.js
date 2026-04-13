/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-highlight-text', { sync: true }), {
        props,
    });
}

describe('src/app/component/base/sw-highlight-text', () => {
    it('renders html-like input as text when no search term is provided', async () => {
        const wrapper = await createWrapper({
            text: '<article>example</article>',
            searchTerm: null,
        });

        expect(wrapper.find('article').exists()).toBe(false);
        expect(wrapper.text()).toContain('<article>example</article>');
    });

    it('keeps html-like input as text while applying highlighting', async () => {
        const wrapper = await createWrapper({
            text: '<article>example</article>',
            searchTerm: 'example',
        });

        expect(wrapper.find('article').exists()).toBe(false);
        expect(wrapper.find('.sw-highlight-text__highlight').exists()).toBe(true);
        expect(wrapper.find('.sw-highlight-text__highlight').text()).toBe('example');
        expect(wrapper.text()).toContain('<article>example</article>');
    });
});
