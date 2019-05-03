import DomAccess from 'src/script/helper/dom-access.helper';
import { REMOVE_BACKDROP_DELAY } from 'src/script/utility/backdrop/backdrop.util';

const PSEUDO_MODAL_CLASS = 'js-pseudo-modal';

// todo: maybe outsource into twig markup
const MODAL_MARKUP = (content) => {
    return `
        <div class="modal"
             tabindex="-1"
             role="dialog">
            <div class="modal-dialog"
                   role="document">
                <div class="modal-content">
                    <div class="modal-header only-close">
                        <div class="modal-close close btn btn-light"
                             data-dismiss="modal"
                             aria-label="Close">
                            <span aria-hidden="true"><i class="fas fa-times"></i></span>
                        </div>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                </div>
            </div>
        </div>
    `;
};

export default class PseudoModalUtil {

    constructor(content, modalMarkup, useBackdrop = true) {
        this._content = content;
        this._modalMarkup = modalMarkup || MODAL_MARKUP;
        this._useBackdrop = useBackdrop;
    }

    /**
     * opens the modal
     *
     * @param {function} cb
     */
    open(cb) {
        this._create();
        setTimeout(this._open.bind(this, cb), REMOVE_BACKDROP_DELAY);
    }

    /**
     * returns the modal element
     *
     * @returns {HTMLElement}
     */
    getModal() {
        if (!this._modal) this._create();

        return this._modal;
    }

    /**
     * updates the modal position
     */
    updatePosition() {
        this._$modal.modal('handleUpdate');
    }

    /**
     * @param {function} cb
     * @private
     */
    _open(cb) {
        this.getModal();
        // register on modal hidden event to remove the ajax modal pseudoModal
        this._$modal.on('hidden.bs.modal', this._modalWrapper.remove);
        this._$modal.on('shown.bs.modal', cb);
        this._$modal.modal({ backdrop: this._useBackdrop });
        this._$modal.modal('show');
    }

    /**
     * insert a temporarily needed wrapper div
     * with the response's html content
     *
     * @returns {HTMLElement}
     *
     * @private
     */
    _create() {
        this._createModalWrapper();
        this._modalWrapper.innerHTML = this._content;
        this._modal = this._createModalMarkup();
        this._$modal = $(this._modal);
        document.body.insertAdjacentElement('beforeend', this._modalWrapper);
    }

    /**
     * creates the modal wrapper
     *
     * @private
     */
    _createModalWrapper() {
        this._modalWrapper = DomAccess.querySelector(document, `.${PSEUDO_MODAL_CLASS}`, false);

        if (!this._modalWrapper) {
            this._modalWrapper = document.createElement('div');
            this._modalWrapper.classList.add(PSEUDO_MODAL_CLASS);
        }
    }

    /**
     * creates the modal markup if
     * it's not existing already
     *
     * @returns {HTMLElement}
     *
     * @private
     */
    _createModalMarkup() {
        const modal = DomAccess.querySelector(this._modalWrapper, '.modal', false);

        if (modal) {
            return modal;
        }

        const content = this._modalWrapper.innerHTML;
        this._modalWrapper.innerHTML = this._modalMarkup(content);
        return DomAccess.querySelector(this._modalWrapper, '.modal');
    }
}
