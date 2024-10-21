import HtmlOffcanvas from 'src/plugin/offcanvas/html-offcanvas.plugin';

/**
 * @package storefront
 */
describe('HtmlOffcanvas tests', () => {

    beforeEach(() => {
        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };
    });

    afterEach(() => {
        jest.useRealTimers();
        document.body.innerHTML = '';
    });

    it('should open with content identified by selector', () => {
        document.body.innerHTML = '<div class="my-offcanvas-content"><p>Super interesting content</p></div>'

        HtmlOffcanvas.open('.my-offcanvas-content', 'start');

        // Ensure exists check works
        expect(HtmlOffcanvas.exists()).toBe(true);

        // Ensure content from DOM is injected into Offcanvas
        expect(document.querySelector('.offcanvas').innerHTML).toBe('<p>Super interesting content</p>');
    });

    it('should error when element cannot be found by selector', () => {
        expect(() => {
            HtmlOffcanvas.open('.not-exist', 'start');
        }).toThrowError('Parent element does not exist!');
    });
});
