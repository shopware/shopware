import DomAccess from 'src/helper/dom-access.helper';
import { REMOVE_BACKDROP_DELAY } from 'src/utility/backdrop/backdrop.util';

const PSEUDO_MODAL_CLASS = 'js-pseudo-modal';
const PSEUDO_MODAL_TEMPLATE_CLASS = 'js-pseudo-modal-template';
const PSEUDO_MODAL_TEMPLATE_CONTENT_CLASS = 'js-pseudo-modal-template-content-element';
const PSEUDO_MODAL_TEMPLATE_TITLE_CLASS = 'js-pseudo-modal-template-title-element';

/**
 * @package storefront
 */
export default class PseudoModalUtil {
    constructor(
        content,
        useBackdrop = true,
        templateSelector = `.${PSEUDO_MODAL_TEMPLATE_CLASS}`,
        templateContentSelector = `.${PSEUDO_MODAL_TEMPLATE_CONTENT_CLASS}`,
        templateTitleSelector = `.${PSEUDO_MODAL_TEMPLATE_TITLE_CLASS}`,
    ) {
        this._content = content;
        this._useBackdrop = useBackdrop;
        this._templateSelector = templateSelector;
        this._templateContentSelector = templateContentSelector;
        this._templateTitleSelector = templateTitleSelector;
    }

    /**
     * opens the modal
     *
     * @param {function} cb
     * @param {Number} delay
     */
    open(cb, delay = REMOVE_BACKDROP_DELAY) {
        this._hideExistingModal();
        this._create();
        setTimeout(this._open.bind(this, cb), delay);
    }

    /**
     * closes the modal
     */
    close() {
        const modal = this.getModal();

        this._modalInstance = bootstrap.Modal.getInstance(modal);
        this._modalInstance.hide();
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
        this._modalInstance.handleUpdate();
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
     * Before opening a new pseudo modal, check if there is any existing pseudo modal already.
     * Hide an existing pseudo modal first to avoid multiple modals or backdrops.
     *
     * @private
     */
    _hideExistingModal() {
        try {
            const existingModalEl = DomAccess.querySelector(document, `.${PSEUDO_MODAL_CLASS} .modal`, false);
            if (!existingModalEl) {
                return;
            }

            const existingModalInstance = bootstrap.Modal.getInstance(existingModalEl);
            if (!existingModalInstance) {
                return;
            }

            existingModalInstance.hide();
        } catch (err) {
            console.warn(`[PseudoModalUtil] Unable to hide existing pseudo modal before opening pseudo modal: ${err.message}`);
        }
    }

    /**
     * @param {function} cb
     * @private
     */
    _open(cb) {
        this.getModal();

        this._modal.addEventListener('hidden.bs.modal', this._modalWrapper.remove);
        this._modal.addEventListener('shown.bs.modal', cb);

        this._modalInstance.show();
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

        this._modalInstance = new bootstrap.Modal(this._modal, {
            backdrop: this._useBackdrop,
        });

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
     * This method is used to set the modal element's title.
     *
     * @param {string} title
     * @private
     */
    _setModalTitle(title = '') {
        try {
            const titleElement = DomAccess.querySelector(this._modalWrapper, this._templateTitleSelector);
            titleElement.innerHTML = title;
        } catch (err) {
            // do nothing
        }
    }

    /**
     * This method is used to set the modal element's content.
     *
     * @private
     */
    _setModalContent(content) {
        const contentElement = DomAccess.querySelector(this._modalWrapper, this._templateContentSelector);
        contentElement.innerHTML = content;

        try {
            const titleElement = DomAccess.querySelector(contentElement, this._templateTitleSelector);
            if (titleElement) {
                this._setModalTitle(titleElement.innerHTML);
                titleElement.parentNode.removeChild(titleElement);
            }
        } catch (err) {
            // do nothing
        }
    }
}
