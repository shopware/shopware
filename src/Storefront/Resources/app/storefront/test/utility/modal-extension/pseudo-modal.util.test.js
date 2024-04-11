import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';

import PseudoModalTemplate from './pseudo-modal.template.html'
import ModalContentTemplate from './modal-content.template.html'

const selector = {
    templateTitle: '.js-pseudo-modal-template-title-element',
}

/**
 * @package storefront
 */
describe('pseudo-modal.util tests', () => {
    let pseudoModal = null;

    function initialModal() {
        return new PseudoModalUtil(ModalContentTemplate);
    }

    beforeEach(() => {
        document.body.innerHTML = PseudoModalTemplate;

        // Mock implementation of insertAdjacentElement because it is currently not supported by jsdom
        document.body.insertAdjacentElement = jest.fn().mockImplementation((position, html) => {
            document.body.appendChild(html);
        });

        jest.useFakeTimers();
        pseudoModal = initialModal();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.useRealTimers();
    });

    test('it should open the modal', () => {
        const spyModalOpen = jest.spyOn(pseudoModal, '_open');

        pseudoModal.open();
        jest.runAllTimers();

        expect(spyModalOpen).toHaveBeenCalled();

        // Ensure opened modal is found in DOM
        const openedModal = document.querySelector('.modal.fade.show');
        expect(openedModal).toBeTruthy();

        // Ensure content is found inside opened modal DOM
        expect(openedModal.querySelector('.modal-body').innerHTML).toContain('<div>Modal content</div>');
    });

    test('it should open the modal with delay', () => {
        const spyModalOpen = jest.spyOn(pseudoModal, '_open');
        const spyCallback = jest.fn();

        pseudoModal.open(spyCallback, 200);
        jest.advanceTimersByTime(210);

        expect(spyModalOpen).toHaveBeenCalled();
        expect(spyCallback).toHaveBeenCalled();

        // Ensure opened modal is found in DOM
        const openedModal = document.querySelector('.modal.fade.show');
        expect(openedModal).toBeTruthy();

        // Ensure content is found inside opened modal DOM
        expect(openedModal.querySelector('.modal-body').innerHTML).toContain('<div>Modal content</div>');
    });

    test('it should close the modal', () => {
        pseudoModal.open();
        jest.runAllTimers();

        // Ensure opened modal is found in DOM
        expect(document.querySelector('.modal.fade.show')).toBeTruthy();

        // Now we close the modal again
        pseudoModal.close();
        jest.runAllTimers();

        // Ensure the modal with "show" class is not present after close
        expect(document.querySelector('.modal.fade.show')).toBeFalsy();
    });

    test('it has title template placeholder in modal header', () => {
        const templateTitle = document.querySelector(selector.templateTitle);

        expect(templateTitle).not.toBeNull();
        expect(templateTitle.textContent).toBe('');
    });

    test('it can move modal title from modal body to header', () => {
        const modal = pseudoModal.getModal();
        const modalTitle = modal.querySelector(`h5${selector.templateTitle}`);

        expect(modalTitle).not.toBeNull();
        expect(modalTitle.textContent).toBe('Modal title');
    });

    test('it can remove modal title from body after moving to header', () => {
        const modal = pseudoModal.getModal();
        const titleElement = modal.querySelectorAll(selector.templateTitle);

        expect(titleElement).toHaveLength(1);
    });

    test('it closes existing modal before opening new modal', () => {
        pseudoModal.open();
        jest.runAllTimers();

        // Ensure first modal is opened
        const openedModal = document.querySelector('.js-pseudo-modal .modal.fade.show');
        expect(openedModal.querySelector('.modal-body').innerHTML).toContain('<div>Modal content</div>');

        // While the first modal is still open, some other modal is opened with new instance of PseudoModalUtil
        const newPseudoModal = new PseudoModalUtil('<div>New modal content</div>');
        newPseudoModal.open();
        jest.runAllTimers();

        // Ensure new modal is opened with new content
        const newModal = document.querySelector('.js-pseudo-modal .modal.fade.show');
        expect(newModal.querySelector('.modal-body').innerHTML).toContain('<div>New modal content</div>');

        // Ensure only a single backdrop exists
        expect(document.querySelectorAll('.modal-backdrop').length).toBe(1);
    });
});
