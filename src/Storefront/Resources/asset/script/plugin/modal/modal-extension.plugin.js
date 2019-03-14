import $ from 'jquery';
import HttpClient from "../../service/http-client.service";
import DomAccess from "../../helper/dom-access.helper";
import ButtonLoadingIndicator from "../loading-indicator/button-loading-indicator.plugin";
import DeviceDetection from "../../helper/device-detection.helper";

const MODAL_REMOTE_DATA_ATTRIBUTE = 'data-url';

/**
 * This class extends the Bootstrap modal functionality by
 * adding an event listener to modal triggers that contain
 * a special "data-url" attribute which is needed to load
 * the modal content by AJAX
 *
 * Notice: The response template needs to have the markup as defined in the Bootstrap docs
 * https://getbootstrap.com/docs/4.3/components/modal/#live-demo
 */
export default class ModalExtension {

    /**
     * Constructor.
     */
    constructor() {
        this._registerEvents();
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
        let modalTriggers = document.querySelectorAll('[data-toggle="modal"]');
        let event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        modalTriggers.forEach(trigger => {
            if (DomAccess.hasAttribute(trigger, MODAL_REMOTE_DATA_ATTRIBUTE)) {
                trigger.addEventListener(event, this._onClickHandleAjaxModal.bind(this));
            }
        });
    }

    /**
     * When clicking/touching the modal trigger the button shall
     * show a loading indicator and an AJAX request needs to be triggered.
     * The response then has to be placed inside the modal which will show up.
     * @param {Event} e
     * @private
     */
    _onClickHandleAjaxModal(e) {
        e.stopPropagation();

        const trigger = e.srcElement;
        const url = DomAccess.getAttribute(trigger, MODAL_REMOTE_DATA_ATTRIBUTE);
        const target = DomAccess.getAttribute(trigger, 'data-target');
        const client = new HttpClient(window.accessKey, window.contextToken);

        // show loading indicator in button
        const indicator = new ButtonLoadingIndicator(trigger);
        indicator.create();

        client.get(url, (response) => {

            // append the temporarily created ajax modal content to the end of the DOM
            const wrapper = document.body.insertAdjacentElement('beforeend', this._getElementFromResponse(response));
            const modal = DomAccess.querySelector(wrapper, target);

            // show modal and remove the ButtonLoadingIndicator
            $(modal).modal('show');
            indicator.remove();

            // register on modal hidden event to remove the
            $(modal).on('hidden.bs.modal', () => {
                // remove ajax modal wrapper
                wrapper.remove();
            });
        });

    }

    /**
     * Prepare a temporarily needed wrapper div
     * to insert the response's html content into
     * @param response
     * @returns {HTMLElement}
     * @private
     */
    _getElementFromResponse(response) {
        let element = document.createElement('div');
        element.classList.add('ajax-modal');
        element.innerHTML = response;

        return element;
    }
}