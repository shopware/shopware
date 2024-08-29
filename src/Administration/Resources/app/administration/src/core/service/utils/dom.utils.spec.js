/**
 * @package admin
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
});
