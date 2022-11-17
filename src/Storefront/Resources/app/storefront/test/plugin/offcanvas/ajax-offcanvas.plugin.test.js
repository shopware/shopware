import Feature from 'src/helper/feature.helper';
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

describe('AjaxOffcanvas tests', () => {

    beforeEach(() => {
        window.PluginManager.initializePlugins = jest.fn();
    });

    afterEach(() => {
        jest.useRealTimers();
        document.body.innerHTML = '';
    });

    /** @deprecated tag:v6.5.0 - Remove const `featureFlags` */
    const featureFlags = [
        { 'v6.5.0.0': false },
        { 'v6.5.0.0': true },
    ];

    /**
     * Run all tests with toggled major flag `v6.5.0.0` to ensure Bootstrap v4 and v5 work as expected.
     * @deprecated tag:v6.5.0 - Remove surrounding forEach
     */
    featureFlags.forEach((flag) => {
        const bsVersion = flag['v6.5.0.0'] ? 'Bootstrap v5' : 'Boostrap v4'

        it(`should open with data from url (POST) (${bsVersion})`, () => {
            Feature.init({ 'v6.5.0.0': flag['v6.5.0.0'] });

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

        it(`should open with data from url (GET) (${bsVersion})`, () => {
            Feature.init({ 'v6.5.0.0': flag['v6.5.0.0'] });

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

        it(`should execute callback after request (${bsVersion})`, () => {
            Feature.init({ 'v6.5.0.0': flag['v6.5.0.0'] });

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

            // Ensure plugins will be re-initialized
            expect(window.PluginManager.initializePlugins).toHaveBeenCalledTimes(1);
        });

        it(`should throw error when no URL is passed (${bsVersion})`, () => {
            Feature.init({ 'v6.5.0.0': flag['v6.5.0.0'] });

            expect(() => AjaxOffcanvas.open()).toThrowError('A url must be given!');
        });
    });
});
