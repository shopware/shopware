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

    it('highlights text correctly when adminEsEnable is true', async () => {
        Shopware.Context.app.adminEsEnable = true;

        const wrapper = await createWrapper({
            text: 'This is a test. Testing, one, two, three.',
            searchTerm: 'test',
        });

        expect(wrapper.findAll('.sw-highlight-text__highlight')).toHaveLength(2);
        expect(wrapper.findAll('.sw-highlight-text__highlight')[0].text()).toBe('test');
        expect(wrapper.findAll('.sw-highlight-text__highlight')[1].text()).toBe('Test');
    });

    it('highlights text with special characters in search term correctly when adminEsEnable is true', async () => {
        Shopware.Context.app.adminEsEnable = true;

        const wrapper = await createWrapper({
            text: 'This is a test for special characters',
            searchTerm: '.*special characters~"',
        });

        expect(wrapper.findAll('.sw-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.sw-highlight-text__highlight')[0].text()).toBe('special characters');
    });

    it('renders plain text when search term only contains elasticsearch special characters', async () => {
        Shopware.Context.app.adminEsEnable = true;

        const wrapper = await createWrapper({
            text: 'This is a test for *special* characters',
            searchTerm: '*',
        });

        expect(wrapper.findAll('.sw-highlight-text__highlight')).toHaveLength(0);
        expect(wrapper.text()).toBe('This is a test for *special* characters');
    });

    it('highlights text with duplicate spaces in search term correctly when adminEsEnable is true', async () => {
        Shopware.Context.app.adminEsEnable = true;

        const wrapper = await createWrapper({
            text: 'This is a test for duplicate spaces.',
            searchTerm: 'duplicate   spaces',
        });

        expect(wrapper.findAll('.sw-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.sw-highlight-text__highlight')[0].text()).toBe('duplicate spaces');
    });

    it('highlights text with AND/OR in search term correctly when adminEsEnable is true', async () => {
        Shopware.Context.app.adminEsEnable = true;

        const wrapper = await createWrapper({
            text: 'This is a test for AND and OR operators.',
            searchTerm: 'test AND OR',
        });

        expect(wrapper.findAll('.sw-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.sw-highlight-text__highlight')[0].text()).toBe('test');
    });

    it('highlights text with plus or minus in search term correctly when adminEsEnable is true', async () => {
        Shopware.Context.app.adminEsEnable = true;

        let wrapper = await createWrapper({
            text: 'This is a test for plus at the start.',
            searchTerm: '+plus',
        });

        expect(wrapper.findAll('.sw-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.sw-highlight-text__highlight')[0].text()).toBe('plus');

        wrapper = await createWrapper({
            text: 'This is a test for plus at the end.',
            searchTerm: 'plus+',
        });

        expect(wrapper.findAll('.sw-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.sw-highlight-text__highlight')[0].text()).toBe('plus');

        wrapper = await createWrapper({
            text: 'This is a test for minus at the start.',
            searchTerm: '-minus',
        });

        expect(wrapper.findAll('.sw-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.sw-highlight-text__highlight')[0].text()).toBe('minus');

        wrapper = await createWrapper({
            text: 'This is a test for minus at the end.',
            searchTerm: 'minus-',
        });

        expect(wrapper.findAll('.sw-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.sw-highlight-text__highlight')[0].text()).toBe('minus');

        wrapper = await createWrapper({
            text: 'This is a test for plus and minus in a word. e.g. for order-number.',
            searchTerm: 'order-number',
        });

        expect(wrapper.findAll('.sw-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.sw-highlight-text__highlight')[0].text()).toBe('order-number');
    });
});
