import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Shopware } from 'shopware';

import 'shopware';
const { default: Modal } = await import('./Modal');

function buildEl() {
    const el = document.createElement('div');
    el.innerHTML = `
        <div class="sw-modal__title"></div>
        <div class="sw-modal__body"></div>
    `;
    return el;
}

describe('Modal', () => {
    let el;
    let modal;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
        modal = new Modal(el, {});
        modal.init();
    });

    describe('init without ajaxUrl', () => {
        it('does not register show.bs.modal listener when ajaxUrl is null', () => {
            const spy = vi.spyOn(el, 'addEventListener');
            const m = new Modal(el, {});
            m.init();

            expect(spy).not.toHaveBeenCalledWith('show.bs.modal', expect.anything());
            expect(spy).not.toHaveBeenCalledWith('shown.bs.modal', expect.anything());
        });
    });

    describe('init with ajaxUrl', () => {
        it('registers show.bs.modal and shown.bs.modal listeners', () => {
            const spy = vi.spyOn(el, 'addEventListener');
            const m = new Modal(el, { ajaxUrl: '/content/fragment' });
            m.init();

            expect(spy).toHaveBeenCalledWith('show.bs.modal', expect.any(Function));
            expect(spy).toHaveBeenCalledWith('shown.bs.modal', expect.any(Function));
        });
    });

    describe('addLoadingState', () => {
        it('sets the modal body to a loading placeholder', () => {
            modal.addLoadingState();

            expect(el.querySelector('.sw-modal__body').innerHTML).toContain('placeholder');
        });

        it('adds loading classes to modal title when setTitleFromAjaxContent is enabled', () => {
            const m = new Modal(el, { ajaxUrl: '/url', setTitleFromAjaxContent: true });
            m.init();
            m.addLoadingState();

            const title = el.querySelector('.sw-modal__title');
            expect(title.classList.contains('placeholder-glow')).toBe(true);
        });

        it('does not modify the title when setTitleFromAjaxContent is false', () => {
            modal.addLoadingState();

            const title = el.querySelector('.sw-modal__title');
            expect(title.classList.contains('placeholder-glow')).toBe(false);
        });
    });

    describe('renderContent', () => {
        it('sets the modal body innerHTML to the provided content', () => {
            modal.renderContent('<p>Hello world</p>');

            expect(el.querySelector('.sw-modal__body').innerHTML).toBe('<p>Hello world</p>');
        });

        it('calls emitInterception before rendering', () => {
            modal.renderContent('<p>test</p>');

            expect(Shopware.emitInterception).toHaveBeenCalledWith(
                'Modal:PreRenderContent',
                { content: '<p>test</p>' },
            );
        });

        it('uses the intercepted content', () => {
            vi.mocked(Shopware.emitInterception).mockReturnValueOnce({ content: '<p>Intercepted</p>' });
            modal.renderContent('<p>Original</p>');

            expect(el.querySelector('.sw-modal__body').innerHTML).toBe('<p>Intercepted</p>');
        });

        it('moves the first headline to the modal title when setTitleFromAjaxContent is enabled', () => {
            const m = new Modal(el, { ajaxUrl: '/url', setTitleFromAjaxContent: true });
            m.init();

            m.renderContent('<h2>Section Title</h2><hr><p>Body content</p>');

            expect(el.querySelector('.sw-modal__title').textContent).toBe('Section Title');
            expect(el.querySelector('.sw-modal__body h2')).toBeNull();
        });
    });

    describe('moveFirstHeadlineToModalTitle', () => {
        it('sets the modal title text from the first headline', () => {
            el.querySelector('.sw-modal__body').innerHTML = '<h3>My Title</h3><p>Content</p>';
            modal.moveFirstHeadlineToModalTitle();

            expect(el.querySelector('.sw-modal__title').textContent).toBe('My Title');
        });

        it('also removes the <hr> following the headline', () => {
            el.querySelector('.sw-modal__body').innerHTML = '<h2>Title</h2><hr><p>Body</p>';
            modal.moveFirstHeadlineToModalTitle();

            expect(el.querySelector('.sw-modal__body hr')).toBeNull();
        });

        it('removes the headline from the body', () => {
            el.querySelector('.sw-modal__body').innerHTML = '<h1>Title</h1><p>Body</p>';
            modal.moveFirstHeadlineToModalTitle();

            expect(el.querySelector('.sw-modal__body h1')).toBeNull();
        });
    });

    describe('fetchContent', () => {
        it('calls renderContent with the fetched HTML on success', async () => {
            const mockFetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue({
                text: () => Promise.resolve('<p>Remote content</p>'),
            });

            const m = new Modal(el, { ajaxUrl: '/content/fragment' });
            m.init();
            m.fetchContent();

            // fetchContent does not return the promise, so wait for the async chain to settle.
            await vi.waitFor(() => {
                expect(el.querySelector('.sw-modal__body').innerHTML).toBe('<p>Remote content</p>');
            });

            mockFetch.mockRestore();
        });
    });
});
