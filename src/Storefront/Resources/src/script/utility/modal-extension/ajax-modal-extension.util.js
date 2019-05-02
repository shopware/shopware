import HttpClient from 'src/script/service/http-client.service';
import DomAccess from 'src/script/helper/dom-access.helper';
import PageLoadingIndicatorUtil from 'src/script/utility/loading-indicator/page-loading-indicator.util';
import { REMOVE_BACKDROP_DELAY } from 'src/script/utility/backdrop/backdrop.util';

const URL_DATA_ATTRIBUTE = 'data-url';
const MODAL_AJAX_CLASS = 'js-ajax-modal';

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

/**
 * This class extends the Bootstrap modal functionality by
 * adding an event listener to modal triggers that contain
 * a special "data-url" attribute which is needed to load
 * the modal content by AJAX
 *
 * Notice: The response template needs to have the markup as defined in the Bootstrap docs
 * https://getbootstrap.com/docs/4.3/components/modal/#live-demo
 */
export default class AjaxModalExtensionUtil {

    /**
     * Constructor.
     */
    constructor(modalMarkup = false) {
        this._client = new HttpClient(window.accessKey, window.contextToken);
        this._registerEvents();
        this._modalMarkup = modalMarkup || MODAL_MARKUP;
    }

    /**
     * Register events
     * @private
     */
    _registerEvents() {
        this._registerAjaxModalExtension();
    }

    /**
     * Handle modal trigger that contain the "data-url" attribute
     * and thus need to load the modal content via AJAX
     * @private
     */
    _registerAjaxModalExtension() {
        const modalTriggers = document.querySelectorAll(`[data-toggle="modal"][${URL_DATA_ATTRIBUTE}]`);
        modalTriggers.forEach(trigger => trigger.addEventListener('click', this._onClickHandleAjaxModal.bind(this)));
    }

    /**
     * When clicking/touching the modal trigger the button shall
     * show a loading indicator and an AJAX request needs to be triggered.
     * The response then has to be placed inside the modal which will show up.
     * @param {Event} event
     * @private
     */
    _onClickHandleAjaxModal(event) {
        event.preventDefault();
        event.stopPropagation();

        const trigger = event.target;
        const url = DomAccess.getAttribute(trigger, URL_DATA_ATTRIBUTE);
        PageLoadingIndicatorUtil.create();
        this._client.get(url, response => this._openModal(response));
    }


    _openModal(response) {
        PageLoadingIndicatorUtil.remove();

        // append the temporarily created ajax modal content to the end of the DOM
        const pseudoModal = this._createPseudoModal(response);
        document.body.insertAdjacentElement('beforeend', pseudoModal);
        let modal = DomAccess.querySelector(pseudoModal, '.modal', false);
        if (!modal) {
            modal = this._createModalWrapper(pseudoModal);
        }
        setTimeout(function () {
            // register on modal hidden event to remove the ajax modal pseudoModal
            $(modal).on('hidden.bs.modal', pseudoModal.remove);
            $(modal).modal({ backdrop: true });
            $(modal).modal('show');
        }, REMOVE_BACKDROP_DELAY);
    }

    /**
     * Prepare a temporarily needed wrapper div
     * to insert the response's html content into
     *
     * @param {string} content
     * @returns {HTMLElement}
     * @private
     */
    _createPseudoModal(content) {
        let element = document.querySelector(`.${MODAL_AJAX_CLASS}`);

        if (!element) {
            element = document.createElement('div');
            element.classList.add(MODAL_AJAX_CLASS);
        }

        element.innerHTML = content;

        return element;
    }

    _createModalWrapper(pseudoModal) {
        const originalContent = pseudoModal.innerHTML;
        pseudoModal.innerHTML = this._modalMarkup(originalContent);
        return DomAccess.querySelector(pseudoModal, '.modal');
    }
}
