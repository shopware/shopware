/**
 * @sw-package framework
 */

import dom from 'src/core/service/utils/dom.utils';

Object.assign(navigator, {
    clipboard: {
        writeText: () => {},
    },
});

describe('src/core/service/utils/dom.utils.ts', () => {
    it('should use the Clipboard API to copy texts', () => {
        jest.spyOn(navigator.clipboard, 'writeText');

        dom.copyStringToClipboard('string to be copied');

        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('string to be copied');
    });

    it('should throw error if Clipboard API is not available', async () => {
        const originalClipboard = navigator.clipboard;

        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: undefined,
        });

        await expect(dom.copyStringToClipboard('string to be copied')).rejects.toThrow(
            'Clipboard functionality is not available. Access the admin via a secure HTTPS connection and try again.',
        );

        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: originalClipboard,
        });
    });
});
