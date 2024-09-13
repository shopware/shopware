import AjaxOffcanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';

// Fake requests from HttpClient
jest.mock('src/service/http-client.service', () => {
    return function () {
        return {
            post: (url, data, callback) => {
                callback('<div>Interesting content from POST request</div>');
            },
            get: (url, callback) => {
                callback('<div>Interesting content from GET request</div>');
            },
        };
    };
});

/**
 * @package storefront
 */
describe('AjaxOffcanvas tests', () => {

    beforeEach(() => {
        window.PluginManager.initializePlugins = jest.fn();

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };
    });

    afterEach(() => {
        jest.useRealTimers();
        document.body.innerHTML = '';
    });

    it('should open with data from url (POST)', () => {
        AjaxOffcanvas.open(
            '/route/action',
            ['foo', 'bar'],
            null,
            'left',
            true,
            350,
            false,
            'my-class'
        );

        // Ensure OffCanvas exists and has content from ajax request
        expect(AjaxOffcanvas.exists()).toBe(true);
        expect(document.querySelector('.offcanvas').innerHTML).toBe('<div>Interesting content from POST request</div>');

        // Ensure plugins will be re-initialized
        expect(window.PluginManager.initializePlugins).toHaveBeenCalledTimes(1);
    });

    it('should open with data from url (GET)', () => {
        AjaxOffcanvas.open(
            '/route/action',
            null,
            null,
            'left',
            true,
            350,
            false,
            'my-class'
        );

        // Ensure OffCanvas exists and has content from ajax request
        expect(AjaxOffcanvas.exists()).toBe(true);
        expect(document.querySelector('.offcanvas').innerHTML).toBe('<div>Interesting content from GET request</div>');

        // Ensure plugins will be re-initialized
        expect(window.PluginManager.initializePlugins).toHaveBeenCalledTimes(1);
    });

    it('should execute callback after request', () => {
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
            'my-class'
        );

        // Ensure OffCanvas exists and callback was executed
        expect(AjaxOffcanvas.exists()).toBe(true);
        expect(callbackFn).toHaveBeenCalledTimes(1);
        expect(document.querySelector('p')).toBeTruthy();

        AjaxOffcanvas.close();

        // Ensure plugins will be re-initialized
        expect(window.PluginManager.initializePlugins).toHaveBeenCalledTimes(1);
    });

    it('should throw error when no URL is passed', () => {
        expect(() => AjaxOffcanvas.open()).toThrowError('A url must be given!');
    });
});
