import { readFileSync } from 'fs';
import { resolve } from 'path';

/** @sw-package discovery */

const template = readFileSync(
    resolve(__dirname, '../../../../../views/storefront/page/content/preview.html.twig'),
    'utf8',
);
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];

describe('content layout preview bridge scrolling', () => {
    let iframe;
    let previewWindow;
    let componentRoot;
    let input;

    function requestScroll(elementId, source = window, messageSource = 'sw-experience-studio-admin') {
        previewWindow.dispatchEvent(new previewWindow.MessageEvent('message', {
            source,
            data: { source: messageSource, type: 'scroll-to-element', elementId },
        }));
    }

    beforeEach(() => {
        iframe = document.createElement('iframe');
        document.body.appendChild(iframe);
        previewWindow = iframe.contentWindow;
        previewWindow.scrollTo = jest.fn();
        Object.defineProperties(previewWindow, {
            innerHeight: { value: 600, configurable: true },
            scrollY: { value: 1000, configurable: true },
            scrollX: { value: 24, configurable: true },
        });

        const parent = previewWindow.document.createElement('section');
        parent.setAttribute('data-element-id', 'parent');
        componentRoot = previewWindow.document.createElement('article');
        componentRoot.setAttribute('data-element-id', 'nested');
        componentRoot.getBoundingClientRect = jest.fn(() => ({ top: 800, height: 100 }));
        input = previewWindow.document.createElement('input');
        componentRoot.appendChild(input);
        parent.appendChild(componentRoot);
        previewWindow.document.body.appendChild(parent);
        previewWindow.eval(script);
    });

    afterEach(() => {
        iframe.remove();
        jest.restoreAllMocks();
    });

    it('centers the requested component root inside the preview without scrolling the admin or moving focus', () => {
        const scrollAdmin = jest.spyOn(window, 'scrollTo').mockImplementation(() => {});
        input.focus();
        requestScroll('nested');

        expect(previewWindow.scrollTo).toHaveBeenCalledWith({ top: 1550, left: 24, behavior: 'smooth' });
        expect(scrollAdmin).not.toHaveBeenCalled();
        expect(previewWindow.document.activeElement).toBe(input);
    });

    it('aligns components taller than the preview with its top edge', () => {
        componentRoot.getBoundingClientRect.mockReturnValue({ top: 800, height: 900 });
        requestScroll('nested');

        expect(previewWindow.scrollTo).toHaveBeenCalledWith({ top: 1800, left: 24, behavior: 'smooth' });
    });

    it('keeps the scroll position at or below the beginning of the preview', () => {
        componentRoot.getBoundingClientRect.mockReturnValue({ top: -950, height: 100 });
        requestScroll('nested');

        expect(previewWindow.scrollTo).toHaveBeenCalledWith({ top: 0, left: 24, behavior: 'smooth' });
    });

    it('matches literal component ids without interpreting them as selectors', () => {
        const elementId = 'element"] [data-element-id="other';
        componentRoot.setAttribute('data-element-id', elementId);
        requestScroll(elementId);

        expect(previewWindow.scrollTo).toHaveBeenCalledTimes(1);
    });

    it.each(['missing', null, undefined, 42, {}])('ignores an unknown or invalid component id: %p', (elementId) => {
        requestScroll(elementId);

        expect(previewWindow.scrollTo).not.toHaveBeenCalled();
    });

    it('only accepts scroll commands from its parent with the admin message source', () => {
        requestScroll('nested', previewWindow);
        requestScroll('nested', window, 'other-source');

        expect(previewWindow.scrollTo).not.toHaveBeenCalled();
    });
});
