import HttpClient from 'src/service/http-client.service';
import DomAccess from 'src/helper/dom-access.helper';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';
import Iterator from 'src/helper/iterator.helper';
import PluginManager from 'src/plugin-system/plugin.manager';
import Feature from 'src/helper/feature.helper';

const URL_DATA_ATTRIBUTE = 'data-url';

/**
 * @deprecated tag:v6.5.0 - Bootstrap v5 renames `data-toggle` attribute to `data-bs-toggle`
 * @see https://getbootstrap.com/docs/5.0/migration/#javascript
 */
const MODAL_TRIGGER_DATA_ATTRIBUTE = Feature.isActive('v6.5.0.0') ? 'data-bs-toggle="modal"' : 'data-toggle="modal"';

/**
 * This class extends the Bootstrap modal functionality by
 * adding an event listener to modal triggers that contain
 * a special "data-url" attribute which is needed to load
 * the modal content by AJAX
 *
 * Notice: The response template needs to have the markup as defined in the Bootstrap docs
 * https://getbootstrap.com/docs/4.3/components/modal/#live-demo
 *
 * @deprecated tag:v6.5.0 - Use AjaxModalPlugin instead
 */
export default class AjaxModalExtensionUtil {

    /**
     * Constructor.
     */
    constructor(modalBackdrop = true) {
        console.warn('Using the AjaxModalExtensionUtil is deprecated and will be removed in 6.5.0. Use AjaxModalPlugin instead');
        this._client = new HttpClient();
        this.useModalBackdrop = modalBackdrop;

        this._registerEvents();
    }

    /**
     * Register events
     * @private
     */
    _registerEvents() {
        this._registerAjaxModalExtension(document);
    }

    /**
     * Handle modal trigger that contain the "data-url" attribute
     * and thus need to load the modal content via AJAX
     * @private
     */
    _registerAjaxModalExtension(element) {
        const modalTriggers = element.querySelectorAll(`[${MODAL_TRIGGER_DATA_ATTRIBUTE}][${URL_DATA_ATTRIBUTE}]`);
        if (modalTriggers) {
            Iterator.iterate(modalTriggers, trigger => trigger.addEventListener('click', this._onClickHandleAjaxModal.bind(this)));
        }
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

        const trigger = event.currentTarget;
        const url = DomAccess.getAttribute(trigger, URL_DATA_ATTRIBUTE);
        PageLoadingIndicatorUtil.create(this.useModalBackdrop);

        this._currentModalClass = trigger.getAttribute('data-modal-class');

        this._client.get(url, response => this._openModal(response));
    }

    /**
     * Opens the ajax modal
     * If called from within a offcanvas, the existing backdrop should not be removed by the PageLoadingIndicatorUtils
     *
     * @param response
     * @private
     */
    _openModal(response) {
        PageLoadingIndicatorUtil.remove(this.useModalBackdrop);
        const pseudoModal = new PseudoModalUtil(response, this.useModalBackdrop);

        pseudoModal.open(() => {
            PluginManager.initializePlugins();
            this._registerAjaxModalExtension(modal);
        });

        const modal = pseudoModal.getModal();

        if (this._currentModalClass) {
            modal.classList.add(this._currentModalClass);
        }
    }
}
