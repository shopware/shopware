import { addReloadReasons, resetNotice, NOTICE_ID } from 'src/dev/twig-hmr/notice';

/**
 * @sw-package framework
 */
describe('dev/twig-hmr/notice', () => {
    beforeEach(() => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        resetNotice();
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    function addToolbar(className, height) {
        const toolbar = document.createElement('div');
        toolbar.className = className;
        toolbar.getBoundingClientRect = () => ({ height });
        document.body.append(toolbar);
    }

    it('sits at the bottom edge without the Symfony toolbar', () => {
        addReloadReasons(['The markup of <nav> changed.']);

        expect(document.getElementById(NOTICE_ID).style.bottom).toBe('calc(0px + 1rem)');
    });

    it('sits above the Symfony toolbar', () => {
        addToolbar('sf-toolbarreset', 36);

        addReloadReasons(['The markup of <nav> changed.']);

        expect(document.getElementById(NOTICE_ID).style.bottom).toBe('calc(36px + 1rem)');
    });

    it('sits above the minimized Symfony toolbar and ignores the hidden full toolbar', () => {
        addToolbar('sf-toolbarreset', 0);
        addToolbar('sf-minitoolbar', 30);

        addReloadReasons(['The markup of <nav> changed.']);

        expect(document.getElementById(NOTICE_ID).style.bottom).toBe('calc(30px + 1rem)');
    });
});
