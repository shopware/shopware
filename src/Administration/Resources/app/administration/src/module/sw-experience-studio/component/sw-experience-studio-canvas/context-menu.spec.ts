import { DOMWrapper, mount } from '@vue/test-utils';
import type { Component } from 'vue';

async function createWrapper(withLayouts = true, preview = '') {
    const frames = withLayouts
        ? [
              { layoutId: 'left', x: -800, y: 0, width: 400, height: 300, viewport: 'desktop', previewEntityId: null },
              { layoutId: 'right', x: 800, y: 0, width: 400, height: 300, viewport: 'desktop', previewEntityId: null },
          ]
        : [];
    const wrapper = mount((await wrapTestComponent('sw-experience-studio-canvas', { sync: true })) as Component, {
        attachTo: document.body,
        slots: { preview },
        props: {
            frames: frames.map((frame) => ({
                id: frame.layoutId,
                layout: { id: frame.layoutId, name: frame.layoutId },
                selectedElementId: null,
                viewport: 'desktop',
                previewEntityId: null,
                isActive: false,
            })),
            workspaceConfiguration: {
                frames,
                previewSalesChannelId: null,
                canvas: { panX: 0, panY: 0, zoom: 0.75 },
            },
        },
    });
    await flushPromises();
    const stage = wrapper.get('.sw-experience-studio-canvas__stage').element;
    Object.defineProperties(stage, {
        clientWidth: { value: 1200 },
        clientHeight: { value: 800 },
    });
    jest.spyOn(stage, 'getBoundingClientRect').mockReturnValue({ width: 1200, height: 800 } as DOMRect);
    await wrapper.setData({ canvasZoom: 0.7 });

    return wrapper;
}

function getMenu() {
    return new DOMWrapper(document.body).get('.sw-experience-studio-canvas__context-menu');
}

describe('Experience Studio free canvas context menu', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    async function openMenu(clientX = 240, clientY = 180) {
        await wrapper.get('.sw-experience-studio-canvas__background').trigger('contextmenu', { clientX, clientY });
        await flushPromises();
    }

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it('opens a Meteor menu at the pointer with layout and zoom action groups', async () => {
        wrapper = await createWrapper();
        await openMenu();

        const menu = getMenu();
        expect(menu.attributes('role')).toBe('menu');
        expect(menu.classes()).toContain('mt-action-menu');
        expect(menu.findAll('[role="group"]')).toHaveLength(2);
        expect(menu.findAll('[role="menuitem"]').map((item) => item.text())).toEqual([
            'global.default.open',
            'sw-experience-studio.workspace.layouts.create',
            'sw-experience-studio.detail.canvas.zoomTo100',
            'sw-experience-studio.detail.canvas.fit',
        ]);
        expect(menu.element.parentElement?.style.transform).toContain('240px, 180px');
    });

    it.each([
        '.sw-experience-studio-canvas__sidebar',
        '.sw-experience-studio-canvas__zoom-controls',
        '.sw-experience-studio-canvas__frame-toolbar',
    ])('keeps the browser context menu outside the free background (%s)', async (selector) => {
        wrapper = await createWrapper();
        const event = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
        wrapper.get(selector).element.dispatchEvent(event);
        await flushPromises();

        expect(event.defaultPrevented).toBe(false);
        expect(document.body.querySelector('.sw-experience-studio-canvas__context-menu')).toBeNull();
    });

    it.each([
        { index: 0, event: 'open-layout' },
        { index: 1, event: 'create-layout' },
    ])('emits $event and dismisses the menu', async ({ index, event }) => {
        wrapper = await createWrapper();
        await openMenu();
        await getMenu().findAll('[role="menuitem"]')[index].trigger('click');
        await flushPromises();

        expect(wrapper.emitted(event)).toEqual([[]]);
        expect(document.body.querySelector('.sw-experience-studio-canvas__context-menu')).toBeNull();
    });

    it.each([
        { index: 2, zoom: '100%' },
        { index: 3, zoom: '55%' },
    ])('runs zoom action $index with the existing smooth transition', async ({ index, zoom }) => {
        wrapper = await createWrapper();
        await openMenu();
        await getMenu().findAll('[role="menuitem"]')[index].trigger('click');
        await flushPromises();

        expect(wrapper.get('.sw-experience-studio-canvas__zoom-controls > span').text()).toBe(zoom);
        expect(wrapper.get('.sw-experience-studio-canvas__world').classes()).toContain('is--viewport-transitioning');
        expect(document.body.querySelector('.sw-experience-studio-canvas__context-menu')).toBeNull();
    });

    it('disables zoom to fit on an empty canvas while keeping Open and Create available', async () => {
        wrapper = await createWrapper(false);
        await openMenu();
        const items = getMenu().findAll('[role="menuitem"]');

        expect(items[0].attributes('data-disabled')).toBeUndefined();
        expect(items[1].attributes('data-disabled')).toBeUndefined();
        expect(items[3].attributes('data-disabled')).toBeDefined();
    });

    it('dismisses with Escape and opens again at a different pointer position', async () => {
        wrapper = await createWrapper();
        await openMenu();
        await getMenu().trigger('keydown', { key: 'Escape' });
        await flushPromises();
        expect(document.body.querySelector('.sw-experience-studio-canvas__context-menu')).toBeNull();

        await openMenu(400, 300);
        expect(getMenu().element.parentElement?.style.transform).toContain('400px, 300px');
    });

    it('moves an already open menu to the latest right-click position', async () => {
        wrapper = await createWrapper();
        await openMenu();
        await openMenu(400, 300);

        expect(document.body.querySelectorAll('.sw-experience-studio-canvas__context-menu')).toHaveLength(1);
        expect(getMenu().element.parentElement?.style.transform).toContain('400px, 300px');
    });

    it('supports keyboard navigation through the menu actions', async () => {
        wrapper = await createWrapper();
        await openMenu();
        const items = getMenu().findAll('[role="menuitem"]');
        await getMenu().trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        expect(document.activeElement).toBe(items[0].element);

        await items[0].trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        expect(document.activeElement).toBe(items[1].element);
    });

    it('dismisses when a preview iframe takes focus without selecting an element', async () => {
        wrapper = await createWrapper(true, '<iframe title="Layout preview"></iframe>');
        await openMenu();
        const iframe = wrapper.get('iframe').element as HTMLIFrameElement;
        const previewDocument = iframe.contentDocument!;
        const button = previewDocument.createElement('button');
        previewDocument.body.appendChild(button);
        button.click();
        await flushPromises();
        expect(document.body.querySelector('.sw-experience-studio-canvas__context-menu')).not.toBeNull();

        // Browsers blur the parent window when focus moves into an iframe.
        // jsdom does not implement that browsing-context focus change.
        window.dispatchEvent(new Event('blur'));
        await flushPromises();

        expect(document.body.querySelector('.sw-experience-studio-canvas__context-menu')).toBeNull();
        expect(wrapper.emitted('frame-activate')).toBeUndefined();

        await openMenu();
        expect(document.body.querySelector('.sw-experience-studio-canvas__context-menu')).not.toBeNull();
    });

    it('removes the window blur listener when the canvas is unmounted', async () => {
        const addListener = jest.spyOn(window, 'addEventListener');
        const removeListener = jest.spyOn(window, 'removeEventListener');
        wrapper = await createWrapper();
        const blurHandler = addListener.mock.calls.find(([event]) => event === 'blur')?.[1];
        expect(blurHandler).toEqual(expect.any(Function));

        wrapper.unmount();

        expect(removeListener).toHaveBeenCalledWith('blur', blurHandler);
    });

    it('dismisses when clicking outside the menu', async () => {
        wrapper = await createWrapper();
        await openMenu();
        await wrapper.get('.sw-experience-studio-canvas__sidebar').trigger('pointerdown', { button: 0 });
        await flushPromises();

        expect(document.body.querySelector('.sw-experience-studio-canvas__context-menu')).toBeNull();
    });
});
