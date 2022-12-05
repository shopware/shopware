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
    const spyInsertAdjacentElement = jest.fn();

    function initialModal() {
        return new PseudoModalUtil(ModalContentTemplate);
    }

    beforeAll(() => {
        document.body.innerHTML = PseudoModalTemplate;
        document.body.insertAdjacentElement = spyInsertAdjacentElement;
    })

    beforeEach(() => {
        pseudoModal = initialModal();
    })

    test('it has title template placeholder in modal header', () => {
        const templateTitle = document.querySelector(selector.templateTitle);

        expect(templateTitle).not.toBeNull();
        expect(templateTitle.textContent).toBe('');
    })

    test('it can move modal title from modal body to header', () => {
        const modal = pseudoModal.getModal();
        const modalTitle = modal.querySelector(`h5${selector.templateTitle}`);

        expect(modalTitle).not.toBeNull();
        expect(modalTitle.textContent).toBe('Modal title');
    })

    test('it can remove modal title from body after moving to header', () => {
        const modal = pseudoModal.getModal();
        const titleElement = modal.querySelectorAll(selector.templateTitle);

        expect(titleElement).toHaveLength(1);
    })
});
