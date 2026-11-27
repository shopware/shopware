import FocusHandler from 'src/helper/focus-handler.helper';
import template from './focus-handler.helper.template.html';

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
            </div>`;

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
            </div>`;

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
            </div>`;

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

    test('should save and resume focus persistent with session storage', () => {
        document.body.innerHTML = `
            <button id="modal-open">Open Modal</button>

            <div class="modal">
                <button id="modal-close">X</button>
            </div>`;

        const modalButton = document.getElementById('modal-open');
        const modalCloseButton = document.getElementById('modal-close');

        // 1. Focus the modal button manually and verify current focus
        modalButton.focus();
        expect(document.activeElement).toBe(modalButton);

        // 2. Save the focus state with key
        focusHandler.saveFocusStatePersistent('test-modal', '#modal-open');
        expect(window.sessionStorage.getItem('sw-last-focus-test-modal')).toBe('#modal-open');

        // 2. Focus the close button manually and verify current focus
        modalCloseButton.focus();
        expect(document.activeElement).toBe(modalCloseButton);

        // 3. Resume the focus state from session storage and verify current focus and storage has been removed
        focusHandler.resumeFocusStatePersistent('test-modal');
        expect(document.activeElement).toBe(modalButton);
        expect(window.sessionStorage.getItem('sw-last-focus-test-modal')).toBeNull();
    });

    test('should show a console error during persistent save when no sufficient parameters are given', () => {
        const consoleSpy = jest.spyOn(console, 'error').mockImplementation();

        focusHandler.saveFocusStatePersistent();

        expect(consoleSpy).toHaveBeenCalledWith('[FocusHandler]: Unable to save focus state. Parameters "focusStorageKey" and "uniqueSelector" are required.');
        consoleSpy.mockRestore();
    });

    describe('getFocusableElements', () => {
        test('returns all focusable elements', () => {
            document.body.innerHTML = template;

            const focusableElements = focusHandler.getFocusableElements();

            expect(focusableElements).toBeInstanceOf(NodeList);
            expect(focusableElements).toHaveLength(5);
            expect(focusableElements[0]).toBeInstanceOf(HTMLAnchorElement);
            expect(focusableElements[1]).toBeInstanceOf(HTMLButtonElement);
            expect(focusableElements[2]).toBeInstanceOf(HTMLInputElement);
            expect(focusableElements[3]).toBeInstanceOf(HTMLSelectElement);
            expect(focusableElements[4]).toBeInstanceOf(HTMLTextAreaElement);
        });

        test('returns the first focusable element', () => {
            document.body.innerHTML = template;

            const focusableElement = focusHandler.getFirstFocusableElement();

            expect(focusableElement).toBeInstanceOf(HTMLAnchorElement);
            expect(focusableElement.textContent).toBe('This is a link and the first focusable element');
        });

        test('returns the last focusable element', () => {
            document.body.innerHTML = template;

            const focusableElement = focusHandler.getLastFocusableElement();

            expect(focusableElement).toBeInstanceOf(HTMLTextAreaElement);
            expect(focusableElement.textContent).toBe('This is a textarea and the last focusable element');
        });

        test('only returns focusable elements inside given parent element', () => {
            document.body.innerHTML = template;

            const parentElement = document.querySelector('.parent-element');
            const focusableElements = focusHandler.getFocusableElements(parentElement);

            expect(focusableElements).toBeInstanceOf(NodeList);
            expect(focusableElements).toHaveLength(2);
            expect(focusableElements[0]).toBeInstanceOf(HTMLInputElement);
            expect(focusableElements[1]).toBeInstanceOf(HTMLSelectElement);
        });
    });

    // @todo: Remove when upstream issue https://github.com/twbs/bootstrap/issues/42503 is resolved.
    describe('_addFocusTrapGuard', () => {
        test('inserts a focus-trap guard directly after the given element', () => {
            document.body.innerHTML = '<div class="modal">Modal</div>';

            const modal = document.querySelector('.modal');
            const guard = focusHandler._addFocusTrapGuard(modal);

            expect(guard).toBeInstanceOf(HTMLDivElement);
            expect(guard.getAttribute('tabindex')).toBe('0');
            expect(guard.classList.contains('js-focus-trap-guard')).toBe(true);
            expect(modal.nextElementSibling).toBe(guard);
            expect(focusHandler._focusTrapGuard).toBe(guard);
        });

        test('returns null and does nothing when no element is given', () => {
            expect(focusHandler._addFocusTrapGuard()).toBeNull();
            expect(focusHandler._focusTrapGuard).toBeNull();
        });

        test('returns null when the given element has no parent node', () => {
            const detachedEl = document.createElement('div');

            expect(focusHandler._addFocusTrapGuard(detachedEl)).toBeNull();
            expect(focusHandler._focusTrapGuard).toBeNull();
        });

        test('ensures there is never more than one focus-trap guard at a time', () => {
            document.body.innerHTML = '<div class="modal">Modal</div>';

            const modal = document.querySelector('.modal');

            const firstGuard = focusHandler._addFocusTrapGuard(modal);
            const secondGuard = focusHandler._addFocusTrapGuard(modal);

            expect(document.querySelectorAll('.js-focus-trap-guard')).toHaveLength(1);
            expect(firstGuard.isConnected).toBe(false);
            expect(focusHandler._focusTrapGuard).toBe(secondGuard);
        });
    });

    describe('setInitialFocus', () => {
        test('focuses the first focusable element within the container', () => {
            document.body.innerHTML = `
                <div class="modal" tabindex="-1">
                    <p>Text</p>
                    <button class="first" disabled>Disabled</button>
                    <button class="second">Close</button>
                    <a class="third" href="#">Link</a>
                </div>`;

            const modal = document.querySelector('.modal');
            const closeButton = document.querySelector('.second');

            expect(focusHandler.setInitialFocus(modal)).toBe(closeButton);
            expect(document.activeElement).toBe(closeButton);
        });

        test('prefers an element marked with autofocus', () => {
            document.body.innerHTML = `
                <div class="modal" tabindex="-1">
                    <button class="close">Close</button>
                    <input class="field" type="text" autofocus>
                </div>`;

            const field = document.querySelector('.field');

            expect(focusHandler.setInitialFocus(document.querySelector('.modal'))).toBe(field);
            expect(document.activeElement).toBe(field);
        });

        test('falls back to the container without focusable content', () => {
            document.body.innerHTML = '<div class="modal" tabindex="-1"><p>Text</p></div>';

            const modal = document.querySelector('.modal');

            expect(focusHandler.setInitialFocus(modal)).toBe(modal);
            expect(document.activeElement).toBe(modal);
        });

        test('keeps the focus on an element that is already focused within the container', () => {
            document.body.innerHTML = `
                <div class="modal" tabindex="-1">
                    <button class="close">Close</button>
                    <a class="link" href="#">Link</a>
                </div>`;

            const link = document.querySelector('.link');
            link.focus();

            expect(focusHandler.setInitialFocus(document.querySelector('.modal'))).toBe(link);
            expect(document.activeElement).toBe(link);
        });

        test('moves the focus from the container itself to its first focusable element', () => {
            document.body.innerHTML = `
                <div class="modal" tabindex="-1">
                    <button class="close">Close</button>
                </div>`;

            const modal = document.querySelector('.modal');
            modal.focus();

            focusHandler.setInitialFocus(modal);

            expect(document.activeElement).toBe(document.querySelector('.close'));
        });

        test('does nothing without a container', () => {
            expect(focusHandler.setInitialFocus(null)).toBeNull();
        });
    });

    describe('_removeFocusTrapGuard', () => {
        test('removes the previously added focus-trap guard', () => {
            document.body.innerHTML = '<div class="modal">Modal</div>';

            const modal = document.querySelector('.modal');
            const guard = focusHandler._addFocusTrapGuard(modal);

            focusHandler._removeFocusTrapGuard();

            expect(guard.isConnected).toBe(false);
            expect(document.querySelector('.js-focus-trap-guard')).toBeNull();
            expect(focusHandler._focusTrapGuard).toBeNull();
        });

        test('does nothing when no focus-trap guard is present', () => {
            expect(() => focusHandler._removeFocusTrapGuard()).not.toThrow();
            expect(focusHandler._focusTrapGuard).toBeNull();
        });
    });
});
