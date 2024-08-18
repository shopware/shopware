import FocusHandler from 'src/helper/focus-handler.helper';

/**
 * @package storefront
 */
describe('focus-handler.helper', () => {
    let focusHandler;
    let emitterMock;

    beforeEach(() => {
        // Mock the emitter
        emitterMock = {
            publish: jest.fn(),
        };
        global.document.$emitter = emitterMock;

        // Create a new instance of FocusHandler
        focusHandler = new FocusHandler();
    });

    test('should save and resume the focus state with the default key', () => {
        document.body.innerHTML = `
            <button id="modal-open">Open Modal</button>

            <div class="modal">
                <button id="modal-close">X</button>
            </div>`

        const modalButton = document.getElementById('modal-open');
        const modalCloseButton = document.getElementById('modal-close');

        modalButton.focus();
        focusHandler.saveFocusState();

        modalCloseButton.focus();
        expect(document.activeElement).toBe(modalCloseButton);

        focusHandler.resumeFocusState();

        expect(document.activeElement).toBe(modalButton);

        expect(focusHandler._focusMap.get('lastFocus')).toBe(modalButton);
        expect(emitterMock.publish).toHaveBeenCalledWith('Focus/StateSaved', {
            focusHistoryKey: 'lastFocus',
            focusEl: modalButton,
        });
    });

    test('should save and resume the focus state with a custom key', () => {
        document.body.innerHTML = `
            <button id="modal-open">Open Modal</button>

            <div class="modal">
                <button id="modal-close">X</button>
            </div>`

        const modalButton = document.getElementById('modal-open');
        const modalCloseButton = document.getElementById('modal-close');

        modalButton.focus();

        focusHandler.saveFocusState('offcanvas');

        modalCloseButton.focus();
        expect(document.activeElement).toBe(modalCloseButton);

        focusHandler.resumeFocusState('offcanvas');

        expect(document.activeElement).toBe(modalButton);

        expect(focusHandler._focusMap.get('offcanvas')).toBe(modalButton);
        expect(emitterMock.publish).toHaveBeenCalledWith('Focus/StateSaved', {
            focusHistoryKey: 'offcanvas',
            focusEl: modalButton,
        });
    });

    test('should save and resume the focus state with a selector', () => {
        document.body.innerHTML = `
            <button id="modal-open">Open Modal</button>

            <div class="modal">
                <button id="modal-close">X</button>
            </div>`

        const modalButton = document.getElementById('modal-open');
        const modalCloseButton = document.getElementById('modal-close');

        modalButton.focus();

        focusHandler.saveFocusState('modal', '#modal-open');

        modalCloseButton.focus();
        expect(document.activeElement).toBe(modalCloseButton);

        focusHandler.resumeFocusState('modal');

        expect(document.activeElement).toBe(modalButton);

        expect(focusHandler._focusMap.get('modal')).toBe('#modal-open');
        expect(emitterMock.publish).toHaveBeenCalledWith('Focus/StateSaved', {
            focusHistoryKey: 'modal',
            focusEl: '#modal-open',
        });
    });

    test('should handle error when trying to set focus', () => {
        const errorMockElement = {
            focus: jest.fn(() => { throw new Error('focus error'); }),
            tagName: 'DIV',
        };

        const consoleSpy = jest.spyOn(console, 'error').mockImplementation();

        focusHandler.setFocus(errorMockElement);

        expect(consoleSpy).toHaveBeenCalledWith(
            '[FocusHandler]: Unable to focus element.',
            expect.any(Error)
        );

        consoleSpy.mockRestore();
    });
});
