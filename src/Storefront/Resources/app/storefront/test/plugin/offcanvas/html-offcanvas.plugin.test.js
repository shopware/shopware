import Feature from 'src/helper/feature.helper';
import HtmlOffcanvas from 'src/plugin/offcanvas/html-offcanvas.plugin';

/**
 * @package storefront
 */
describe('HtmlOffcanvas tests', () => {

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

        it(`should open with content identified by selector (${bsVersion})`, () => {
            Feature.init({ 'v6.5.0.0': flag['v6.5.0.0'] });

            document.body.innerHTML = '<div class="my-offcanvas-content"><p>Super interesting content</p></div>'

            HtmlOffcanvas.open('.my-offcanvas-content', 'start');

            // Ensure exists check works
            expect(HtmlOffcanvas.exists()).toBe(true);

            // Ensure content from DOM is injected into Offcanvas
            expect(document.querySelector('.offcanvas').innerHTML).toBe('<p>Super interesting content</p>');
        });

        it(`should error when element cannot be found by selector (${bsVersion})`, () => {
            Feature.init({ 'v6.5.0.0': flag['v6.5.0.0'] });

            expect(() => {
                HtmlOffcanvas.open('.not-exist', 'start');
            }).toThrowError('Parent element does not exist!');
        });
    });
});
