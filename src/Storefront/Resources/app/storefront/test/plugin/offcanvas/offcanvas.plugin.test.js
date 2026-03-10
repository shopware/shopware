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

    it('should add aria-labelledby attribute to the OffCanvas', () => {
        jest.useFakeTimers();

        OffCanvas.open(
            '<div class="offcanvas-body">Lorem ipsum<div data-id="off-canvas-headline">Nice headline</div></div>',
            () => {},
            'right',
            true,
            100,
            true,
            'custom-class',
        );
        jest.runAllTimers();

        const offCanvasElement = document.querySelector('.offcanvas');

        // Should have aria-labelledby attribute on OffCanvas element
        expect(offCanvasElement.getAttribute('aria-labelledby')).toBe('off-canvas-headline');
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

    it('removes only offcanvas from the DOM created by OffCanvasSingleton and ignores other Bootstrap offcanvas', () => {
        // Add a hard-coded Bootstrap offcanvas to the HTML which must not be removed by OffCanvasSingleton
        document.body.innerHTML = `
            <button class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasExample" aria-controls="offcanvasExample">
                Open Bootstrap offcanvas
            </button>

            <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasExampleLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="offcanvasExampleLabel">Offcanvas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    Bootstrap offcanvas
                </div>
            </div>
        `;

        jest.useFakeTimers();

        // Open the shopware OffCanvas via OffCanvasSingleton
        OffCanvas.open('Interesting content');
        jest.runAllTimers();

        // Ensue shopware OffCanvas was opened
        expect(OffCanvas.exists()).toBe(true);
        expect(document.querySelector('.js-offcanvas-singleton').classList.contains('show')).toBe(true);

        // Ensure hard-coded Bootstrap offcanvas is also present in the DOM. It must not be removed by "_removeExistingOffCanvas".
        expect(document.getElementById('offcanvasExample')).toBeTruthy();

        // Close the shopware OffCanvas
        OffCanvas.close();
        jest.runAllTimers();

        // Ensure shopware OffCanvas is no longer existing in the DOM
        expect(document.querySelector('.js-offcanvas-singleton')).toBeFalsy();
        expect(OffCanvas.exists()).toBe(false);

        // Ensure the hard-coded Bootstrap offcanvas is still in the DOM
        expect(document.getElementById('offcanvasExample')).toBeTruthy();
    });

    it('should properly dispose Bootstrap instances when removing existing offcanvas', () => {
        jest.useFakeTimers();

        // Open first offcanvas
        OffCanvas.open('First offcanvas');
        jest.runAllTimers();

        const firstOffcanvas = document.querySelector('.js-offcanvas-singleton');
        expect(firstOffcanvas).toBeTruthy();

        // Mock bootstrap.Offcanvas.getInstance and dispose
        const mockDispose = jest.fn();
        const mockInstance = { dispose: mockDispose };
        const getInstanceSpy = jest.spyOn(bootstrap.Offcanvas, 'getInstance').mockReturnValue(mockInstance);

        // Open second offcanvas (should remove first one)
        OffCanvas.open('Second offcanvas');
        jest.runAllTimers();

        // Verify dispose was called on the first offcanvas instance
        expect(getInstanceSpy).toHaveBeenCalledWith(firstOffcanvas);
        expect(mockDispose).toHaveBeenCalled();

        // Verify only one offcanvas exists
        const allOffcanvas = document.querySelectorAll('.js-offcanvas-singleton');
        expect(allOffcanvas.length).toBe(1);
        expect(allOffcanvas[0].innerHTML).toBe('Second offcanvas');

        // Cleanup
        getInstanceSpy.mockRestore();
    });
});
