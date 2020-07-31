import HttpClient from 'src/service/http-client.service';
import Plugin from 'src/plugin-system/plugin.class';
import PluginManager from 'src/plugin-system/plugin.manager';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';
import DeviceDetection from 'src/helper/device-detection.helper';
import DomAccess from 'src/helper/dom-access.helper';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';

/**
 * This class extends the Bootstrap modal functionality by
 * adding an event listener to modal triggers that contain
 * a special "data-url" attribute which is needed to load
 * the modal content by AJAX
 *
 * Notice: The response template needs to have the markup as defined in the Bootstrap docs
 * https://getbootstrap.com/docs/4.3/components/modal/#live-demo
 */
export default class UrlModalPlugin extends Plugin {

    static options = {
        modalBackdrop: true,

        urlAttribute: 'data-url',

        modalClassAttribute: 'data-modal-class',

        modalClass: null
    };

    init() {
        this._registerEvents();
    }

    /**
     * Register events
     * @private
     */
    _registerEvents() {
        const eventType = (DeviceDetection.isTouchDevice()) ? 'touchend' : 'click';

        this.el.removeEventListener('click', this._onClickHandleAjaxModal.bind(this));
        this.el.removeEventListener('touchend', this._onClickHandleAjaxModal.bind(this));
        this.el.addEventListener(eventType, this._onClickHandleAjaxModal.bind(this));
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
        PageLoadingIndicatorUtil.create(this.options.modalBackdrop);

        const client = new HttpClient();
        const url = DomAccess.getAttribute(this.el, this.options.urlAttribute);
        const modalClasses = [DomAccess.getAttribute(this.el, this.options.modalClassAttribute), this.options.modalClass];

        client.get(url, response => this._openModal(response, modalClasses));
    }

    /**
     * Opens the ajax modal
     * If called from within a offcanvas, the existing backdrop should not be removed by the PageLoadingIndicatorUtils
     *
     * @param response
     * @param {Array<String>} classes
     * @private
     */
    _openModal(response, classes) {
        PageLoadingIndicatorUtil.remove(this.options.modalBackdrop);
        const pseudoModal = new PseudoModalUtil(response, this.options.modalBackdrop);

        pseudoModal.open(() => {
            const modal = pseudoModal.getModal();
            modal.classList.add(...classes);
            PluginManager.initializePlugins();
            this.$emitter.publish('urlModalShow', { modal });
        });
    }
}
