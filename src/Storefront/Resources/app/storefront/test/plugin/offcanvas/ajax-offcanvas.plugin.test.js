import AjaxOffcanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';

/**
 * @package storefront
 */
describe('AjaxOffcanvas tests', () => {

    beforeEach(() => {
        window.PluginManager.initializePluginsInParentElement = jest.fn();

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
            // @todo: Remove when upstream issue https://github.com/twbs/bootstrap/issues/42503 is resolved.
            _addFocusTrapGuard: jest.fn(),
            _removeFocusTrapGuard: jest.fn(),
        };
    });

    afterEach(() => {
        jest.useRealTimers();
        document.body.innerHTML = '';
    });

    it('should open with data from url (POST)', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('<div>Interesting content from POST request</div>'),
            }),
        );

        AjaxOffcanvas.open(
            '/route/action',
            ['foo', 'bar'],
            null,
            'left',
            true,
            350,
            false,
            'my-class',
        );
        await new Promise(process.nextTick);

        // Ensure OffCanvas exists and has content from ajax request
        expect(AjaxOffcanvas.exists()).toBe(true);
        expect(document.querySelector('.offcanvas').innerHTML).toBe('<div>Interesting content from POST request</div>');

        // The Twig hot reload must not send a POST request again
        expect(document.querySelector('.offcanvas').dataset.swSourceUrl).toBeUndefined();

        // Ensure plugins will be re-initialized within the offcanvas
        expect(window.PluginManager.initializePluginsInParentElement).toHaveBeenCalledTimes(1);
    });

    it('should open with data from url (GET)', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('<div>Interesting content from GET request</div>'),
            }),
        );

        AjaxOffcanvas.open(
            '/route/action',
            null,
            null,
            'left',
            true,
            350,
            false,
            'my-class',
        );
        await new Promise(process.nextTick);

        // Ensure OffCanvas exists and has content from ajax request
        expect(AjaxOffcanvas.exists()).toBe(true);
        expect(document.querySelector('.offcanvas').innerHTML).toBe('<div>Interesting content from GET request</div>');

        // The Twig hot reload renders the content again from this URL
        expect(document.querySelector('.offcanvas').dataset.swSourceUrl).toBe('/route/action');

        // Ensure plugins will be re-initialized within the offcanvas
        expect(window.PluginManager.initializePluginsInParentElement).toHaveBeenCalledTimes(1);
    });

    it('should execute callback after request', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('<div>Interesting content from GET request</div>'),
            }),
        );

        const callbackFn = jest.fn(() => {
            const el = document.createElement('p');
            document.body.appendChild(el);
        });

        AjaxOffcanvas.open(
            '/route/action',
            null,
            callbackFn,
            'left',
            true,
            350,
            false,
            'my-class',
        );
        await new Promise(process.nextTick);

        // Ensure OffCanvas exists and callback was executed
        expect(AjaxOffcanvas.exists()).toBe(true);
        expect(callbackFn).toHaveBeenCalledTimes(1);
        expect(document.querySelector('p')).toBeTruthy();

        AjaxOffcanvas.close();

        // Ensure plugins will be re-initialized within the offcanvas
        expect(window.PluginManager.initializePluginsInParentElement).toHaveBeenCalledTimes(1);
    });

    it('should load content without an open offcanvas', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('<div>Content</div>'),
            }),
        );

        expect(() => AjaxOffcanvas.setContent('/route/action', null)).not.toThrow();
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(AjaxOffcanvas.exists()).toBe(false);
    });

    it('should throw error when no URL is passed', () => {
        expect(() => AjaxOffcanvas.open()).toThrow('A url must be given!');
    });
});
