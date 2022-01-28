/**
 * @jest-environment jsdom
 */
import Feature from 'src/helper/feature.helper';
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';

describe('OffCanvas tests', () => {

    beforeEach(() => {
        /** @deprecated tag:v6.5.0 - Feature flag reset will be removed. */
        Feature.init({ 'v6.5.0.0': false });
    });

    afterEach(() => {
        jest.useRealTimers();
        document.body.innerHTML = '';
    });

    /** @deprecated tag:v6.5.0 - Test case will be removed. */
    it('should create and open OffCanvas with content (Bootstrap v4)', () => {
        jest.useFakeTimers();

        OffCanvas.open(
            'Interesting content',
            () => {},
            'right',
            true,
            100,
            true,
            'custom-class'
        );
        jest.runAllTimers();

        // Ensure offCanvas was injected to the DOM by selecting it
        const offCanvasElement = document.querySelector('.offcanvas');

        // Ensure exists check works
        expect(OffCanvas.exists()).toBe(true);

        // Ensure OffCanvas has all configured CSS classes
        expect(offCanvasElement.classList.contains('is-right')).toBe(true);
        expect(offCanvasElement.classList.contains('custom-class')).toBe(true);
        expect(offCanvasElement.classList.contains('is-open')).toBe(true);

        // Ensure the OffCanvas content is set
        expect(offCanvasElement.innerHTML).toBe('Interesting content');
    });

    it('should create and open OffCanvas with content (Bootstrap v5)', () => {
        /** @deprecated tag:v6.5.0 - Feature flag reset will be removed. */
        Feature.init({ 'v6.5.0.0': true });

        jest.useFakeTimers();

        OffCanvas.open(
            'Interesting content',
            () => {},
            'end',
            true,
            100,
            true,
            'custom-class'
        );
        jest.runAllTimers();

        // Ensure offCanvas was injected to the DOM by selecting it
        const offCanvasElement = document.querySelector('.offcanvas');

        // Ensure exists check works
        expect(OffCanvas.exists()).toBe(true);

        // Ensure OffCanvas has all configured CSS classes
        expect(offCanvasElement.classList.contains('offcanvas-end')).toBe(true);
        expect(offCanvasElement.classList.contains('custom-class')).toBe(true);

        // Ensure accessibility attrs are set
        expect(offCanvasElement.getAttribute('tabindex')).toBe('-1');
        expect(offCanvasElement.getAttribute('aria-modal')).toBe('true');
        expect(offCanvasElement.getAttribute('role')).toBe('dialog');

        // Ensure the OffCanvas content is set
        expect(offCanvasElement.innerHTML).toBe('Interesting content');
    });

    it('should close the OffCanvas', () => {
        jest.useFakeTimers();

        // Open the OffCanvas
        OffCanvas.open(
            'Interesting content',
            () => {},
            'right',
            true,
            100,
            true,
            'custom-class'
        );
        jest.runAllTimers();

        // Ensue OffCanvas was opened
        expect(document.querySelector('.offcanvas')).toBeTruthy();
        expect(document.querySelector('.offcanvas').classList.contains('is-open')).toBe(true);
        expect(OffCanvas.exists()).toBe(true);

        // Close the OffCanvas
        OffCanvas.close();
        jest.runAllTimers();

        // Ensure OffCanvas is no longer existing in the DOM
        expect(document.querySelector('.offcanvas')).toBeFalsy();
        expect(OffCanvas.exists()).toBe(false);
    });

    it('should be able to set additional CSS classes', () => {
        jest.useFakeTimers();

        OffCanvas.open(
            'Interesting content',
            () => {},
            'right',
            true,
            100,
            true,
            'custom-class'
        );
        jest.runAllTimers();

        const offCanvasElement = document.querySelector('.offcanvas');

        // Additional class should not exist in the beginning
        expect(offCanvasElement.classList.contains('fancy-class')).toBe(false);

        // Set additional class
        OffCanvas.setAdditionalClassName('fancy-class');

        // Ensure additional class was set on OffCanvas element
        expect(offCanvasElement.classList.contains('fancy-class')).toBe(true);
    });

    it('should be able to update the content for already existing OffCanvas', () => {
        jest.useFakeTimers();

        OffCanvas.open(
            'Interesting content',
            () => {},
            'right',
            true,
            100,
            true,
            'custom-class'
        );
        jest.runAllTimers();

        const offCanvasElement = document.querySelector('.offcanvas');

        // Should have initial content in the beginning
        expect(offCanvasElement.innerHTML).toBe('Interesting content');

        // Set other content
        OffCanvas.setContent('Even more interesting content');

        // Ensure other content was set
        expect(offCanvasElement.innerHTML).toBe('Even more interesting content');
    });

    it('should be able to pass initial CSS classes as an array', () => {
        jest.useFakeTimers();

        OffCanvas.open(
            'Interesting content',
            () => {},
            'right',
            true,
            100,
            true,
            ['super-sticky', 'extra-wide'] // Pass CSS as array
        );
        jest.runAllTimers();

        const offCanvasElement = document.querySelector('.offcanvas');

        // Should have CSS classes from array on OffCanvas element
        expect(offCanvasElement.classList.contains('super-sticky')).toBe(true);
        expect(offCanvasElement.classList.contains('extra-wide')).toBe(true);
    });

    it('should throw an error when no string or array is passed for CSS classes', () => {
        expect(() => {
            OffCanvas.open(
                'Interesting content',
                () => {},
                'right',
                true,
                100,
                true,
                { foo: 'Not allowed' } // Cause some trouble
            )
        }).toThrowError('The type "object" is not supported. Please pass an array or a string.');
    });
});
