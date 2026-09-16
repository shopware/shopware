/**
 * @sw-package framework
 *
 * @deprecated tag:v6.8.0 - Will be removed together with sw-text-editor, use mt-text-editor instead.
 */

import { mount } from '@vue/test-utils';

let selectionRect = { top: 100, left: 100, width: 50, height: 20 };

async function createWrapper() {
    document.body.innerHTML = '<div id="app"></div>';

    return mount(await wrapTestComponent('sw-text-editor', { sync: true }), {
        attachTo: document.getElementById('app'),
        props: {
            isInlineEdit: true,
        },
        global: {
            stubs: {
                'sw-text-editor-toolbar': await wrapTestComponent('sw-text-editor-toolbar'),
                'sw-text-editor-toolbar-button': await wrapTestComponent('sw-text-editor-toolbar-button'),
                'sw-text-editor-link-menu': await wrapTestComponent('sw-text-editor-link-menu'),
                'sw-text-editor-table-toolbar': true,
                'sw-text-editor-toolbar-table-button': true,
                'sw-entity-single-select': true,
                'sw-category-tree-field': true,
                'sw-media-field': true,
                'sw-code-editor': true,
            },
        },
    });
}

async function selectEditorContent(wrapper) {
    const contentEditor = wrapper.find('.sw-text-editor__content-editor');

    await wrapper.trigger('click');
    contentEditor.element.innerHTML = '<p id="editorText">foo-bar</p>';
    await contentEditor.trigger('input');

    const range = document.createRange();
    range.setStart(document.getElementById('editorText').firstChild, 0);
    range.setEnd(document.getElementById('editorText').firstChild, 7);
    document.getSelection().addRange(range);

    document.dispatchEvent(new Event('mouseup'));
    await flushPromises();
}

function toolbar() {
    return document.querySelector('.sw-text-editor-toolbar');
}

function toolbarTop() {
    return parseFloat(toolbar().style.top);
}

function linkMenu() {
    return document.querySelector('.sw-text-editor-toolbar-button__link-menu');
}

async function clickToolbarButton(type) {
    document
        .querySelector(`.sw-text-editor-toolbar-button__type-${type} .sw-text-editor-toolbar-button__icon`)
        .dispatchEvent(new Event('click', { bubbles: true }));

    await flushPromises();
}

async function scrollFrom(element) {
    element.dispatchEvent(new Event('scroll'));
    await flushPromises();
}

describe('src/app/component/form/sw-text-editor/sw-text-editor-toolbar', () => {
    let wrapper;

    beforeAll(() => {
        Object.defineProperty(global.Element.prototype, 'innerText', {
            get() {
                return this.textContent;
            },
            configurable: true,
        });

        Range.prototype.getBoundingClientRect = () => ({ ...selectionRect });

        document.execCommand = jest.fn(() => true);
    });

    beforeEach(() => {
        selectionRect = { top: 100, left: 100, width: 50, height: 20 };
    });

    afterEach(async () => {
        await wrapper?.unmount();
        wrapper = null;
        document.getSelection().removeAllRanges();
    });

    it('keeps the link menu open when the url field scrolls', async () => {
        wrapper = await createWrapper();
        await selectEditorContent(wrapper);
        await clickToolbarButton('link');

        expect(linkMenu()).not.toBeNull();

        const longUrl = `https://www.example.com/${'a'.repeat(80)}`;
        const linkTarget = document.querySelector('#sw-field--linkTarget');
        linkTarget.value = longUrl;
        linkTarget.dispatchEvent(new Event('input', { bubbles: true }));
        await flushPromises();

        // An open link menu makes the toolbar tall enough for the recalculated position to end up
        // above the viewport, which is the state that closes any expanded menu.
        Object.defineProperty(toolbar(), 'clientHeight', { value: 300, configurable: true });

        await scrollFrom(linkTarget);

        expect(linkMenu()).not.toBeNull();
        expect(document.querySelector('#sw-field--linkTarget').value).toBe(longUrl);
    });

    it('repositions the toolbar on scroll while no menu is open', async () => {
        wrapper = await createWrapper();
        await selectEditorContent(wrapper);

        const topBeforeScroll = toolbarTop();
        expect(Number.isNaN(topBeforeScroll)).toBe(false);

        selectionRect = { ...selectionRect, top: selectionRect.top + 100 };
        await scrollFrom(wrapper.find('.sw-text-editor__content-editor').element);

        expect(toolbarTop()).toBe(topBeforeScroll + 100);
    });

    it('repositions the toolbar again after another toolbar action closed the link menu', async () => {
        wrapper = await createWrapper();
        await selectEditorContent(wrapper);
        await clickToolbarButton('link');

        expect(linkMenu()).not.toBeNull();

        await clickToolbarButton('bold');

        expect(linkMenu()).toBeNull();

        const topBeforeScroll = toolbarTop();

        selectionRect = { ...selectionRect, top: selectionRect.top + 100 };
        await scrollFrom(wrapper.find('.sw-text-editor__content-editor').element);

        expect(toolbarTop()).toBe(topBeforeScroll + 100);
    });
});
