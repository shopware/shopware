import DomAccess from 'src/helper/dom-access.helper';
import { REMOVE_BACKDROP_DELAY } from 'src/utility/backdrop/backdrop.util';

const PSEUDO_MODAL_CLASS = 'js-pseudo-modal';
const PSEUDO_MODAL_TEMPLATE_CLASS = 'js-pseudo-modal-template';
const PSEUDO_MODAL_TEMPLATE_CONTENT_CLASS = 'js-pseudo-modal-template-content-element';

export default class PseudoModalUtil {
    constructor(
        content,
        useBackdrop = true,
        templateSelector = `.${PSEUDO_MODAL_TEMPLATE_CLASS}`,
        templateContentSelector = `.${PSEUDO_MODAL_TEMPLATE_CONTENT_CLASS}`
    ) {
        this._content = content;
        this._useBackdrop = useBackdrop;
        this._templateSelector = templateSelector;
        this._templateContentSelector = templateContentSelector;
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
     * opens the modal
     *
     */
    close() {
        const modal = this.getModal();
        $(modal).modal('hide');
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
     * This method can be used to update a modal's content.
     * A callback may be provided, for example to re-initialise all plugins once
     * the markup is changed.
     *
     * @param {string} content
     * @param {function} callback
     */
    updateContent(content, callback) {
        this._content = content;
        this._setModalContent(content);
        this.updatePosition();

        if (typeof callback === 'function') {
            callback.bind(this)();
        }
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
        this._modalMarkupEl = DomAccess.querySelector(document, this._templateSelector);
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
        this._modalWrapper.innerHTML = this._modalMarkupEl.innerHTML;

        this._setModalContent(content);

        return DomAccess.querySelector(this._modalWrapper, '.modal');
    }

    /**
     * This method is used to set the modal element's content.
     *
     * @private
     */
    _setModalContent(content) {
        const contentElement = DomAccess.querySelector(this._modalWrapper, this._templateContentSelector);
        contentElement.innerHTML = content;
    }
}
