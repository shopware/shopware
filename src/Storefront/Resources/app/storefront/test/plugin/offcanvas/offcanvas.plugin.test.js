import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';

/**
 * @package storefront
 */
describe('OffCanvas tests', () => {

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

    it('should create and open with content', () => {
        jest.useFakeTimers();

        OffCanvas.open('Interesting content');
        jest.runAllTimers();

        // Ensure offCanvas was injected to the DOM by selecting it
        const offCanvasElement = document.querySelector('.offcanvas');

        // Ensure exists check works
        expect(OffCanvas.exists()).toBe(true);

        // Ensure OffCanvas has all configured CSS classes
        expect(offCanvasElement.classList.contains('show')).toBe(true);

        // Ensure accessibility attrs are set
        expect(offCanvasElement.getAttribute('aria-modal')).toBe('true');
        expect(offCanvasElement.getAttribute('role')).toBe('dialog');

        // Ensure the OffCanvas content is set
        expect(offCanvasElement.innerHTML).toBe('Interesting content');
    });

    it('should close', () => {
        jest.useFakeTimers();

        // Open the OffCanvas
        OffCanvas.open('Interesting content');
        jest.runAllTimers();

        // Ensue OffCanvas was opened
        expect(OffCanvas.exists()).toBe(true);
        expect(document.querySelector('.offcanvas').classList.contains('show')).toBe(true);

        // Close the OffCanvas
        OffCanvas.close();
        jest.runAllTimers();

        // Ensure OffCanvas is no longer existing in the DOM
        expect(document.querySelector('.offcanvas')).toBeFalsy();
        expect(OffCanvas.exists()).toBe(false);
    });

    it('should close via click on backdrop', () => {
        jest.useFakeTimers();

        // Open the OffCanvas
        OffCanvas.open('Interesting content');
        jest.runAllTimers();

        // Ensue OffCanvas was opened
        expect(OffCanvas.exists()).toBe(true);

        const backdrop = document.querySelector('.offcanvas-backdrop');
        const clickEvent = new MouseEvent('mousedown', {
            view: window,
            bubbles: true,
            cancelable: true,
            clientX: 0,
        });

        backdrop.dispatchEvent(clickEvent);
        jest.runAllTimers();

        // Ensure OffCanvas is no longer existing in the DOM
        expect(OffCanvas.exists()).toBe(false);
        expect(document.querySelector('.offcanvas')).toBeFalsy();
    });

    it('should not close via click on backdrop when configured', () => {
        jest.useFakeTimers();

        // Open the OffCanvas
        OffCanvas.open(
            'Interesting content',
            null,
            'right',
            false // Don't allow close on backdrop
        );
        jest.runAllTimers();

        // Ensue OffCanvas was opened
        expect(OffCanvas.exists()).toBe(true);

        const backdrop = document.querySelector('.offcanvas-backdrop');
        const clickEvent = new MouseEvent('mousedown', {
            view: window,
            bubbles: true,
            cancelable: true,
            clientX: 0,
        });

        // Try to close OffCanvas via backdrop
        backdrop.dispatchEvent(clickEvent);
        jest.runAllTimers();

        // Ensure OffCanvas is still in the DOM
        expect(OffCanvas.exists()).toBe(true);
        expect(document.querySelector('.offcanvas')).toBeTruthy();
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

        // Only initially configured class should be given
        expect(offCanvasElement.classList.contains('fancy-class')).toBe(false);
        expect(offCanvasElement.classList.contains('custom-class')).toBe(true);

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

    it('should close when clicking on close-trigger element', () => {
        jest.useFakeTimers();

        // Set content containing a close trigger button with class "js-offcanvas-close"
        OffCanvas.open(
            '<button class="js-offcanvas-close">Close</button><div class="offcanvas-body">Lorem ipsum</div>',
        );

        jest.runAllTimers();

        const closeBtn = document.querySelector('.js-offcanvas-close');

        // Click on the close button
        closeBtn.dispatchEvent(new Event('click', { bubbles: true }));

        jest.runAllTimers();

        // OffCanvas should not exist after closing it via the close button
        expect(OffCanvas.exists()).toBe(false);
    });

    it('should be able to pass initial CSS classes as an array', () => {
        jest.useFakeTimers();

        OffCanvas.open(
            'Interesting content',
            () => {},
            'right',
            true,
            350,
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

    const offCanvasPositions = [
        {  passedPosition: 'start', expectedPositionClass: 'offcanvas-start' },
        {  passedPosition: 'end', expectedPositionClass: 'offcanvas-end' },
        {  passedPosition: 'top', expectedPositionClass: 'offcanvas-top' },
        {  passedPosition: 'bottom', expectedPositionClass: 'offcanvas-bottom' },

        // Ensure backwards compatible positions "left" and "right"
        {  passedPosition: 'left', expectedPositionClass: 'offcanvas-start' },
        {  passedPosition: 'right', expectedPositionClass: 'offcanvas-end' },
    ];

    offCanvasPositions.forEach((position) => {
        it(`should open with position "${position.passedPosition}"`, () => {
            jest.useFakeTimers();

            OffCanvas.open(
                'Interesting content',
                () => {},
                position.passedPosition,
            );
            jest.runAllTimers();

            // Should have the correct position class
            expect(document.querySelector('.offcanvas').classList.contains(position.expectedPositionClass)).toBe(true);
        });
    });
});
