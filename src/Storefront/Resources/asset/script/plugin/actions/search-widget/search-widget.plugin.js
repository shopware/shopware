import DomAccess from "../../../helper/dom-access.helper";
import Debouncer from "../../../helper/debouncer.helper";
import HttpClient from "../../../service/http-client.service";
import Plugin from "../../../helper/plugin.helper";
import InputLoadingIndicator from "../../loading-indicator/input-loading-indicator.plugin";
import DeviceDetection from "../../../helper/device-detection.helper";
import ArrowNavigationHelper from "./helper/arrow-navigation.helper";

const SEARCH_WIDGET_SELECTOR = '.js-search-form';
const SEARCH_WIDGET_RESULTS_SELECTOR = '.js-search-results';
const SEARCH_WIDGET_RESULT_ITEM_SELECTOR = '.js-result';
const SEARCH_WIDGET_INPUT_FIELD_SELECTOR = "input[type=search]";
const SEARCH_WIDGET_URL_DATA_ATTRIBUTE = 'data-url';

const SEARCH_WIDGET_DELAY = 250;
const SEARCH_WIDGET_MIN_CHARS = 3;

export default class SearchWidget extends Plugin {


    /**
     * Constructor.
     */
    constructor() {
        super();

        this._client = new HttpClient(window.accessKey, window.contextToken);
        this._form = DomAccess.querySelector(document, SEARCH_WIDGET_SELECTOR);
        this._inputField = DomAccess.querySelector(this._form, SEARCH_WIDGET_INPUT_FIELD_SELECTOR);
        this._url = DomAccess.getAttribute(this._form, SEARCH_WIDGET_URL_DATA_ATTRIBUTE);

        // initialize the arrow navigation
        this._navigationHelper = new ArrowNavigationHelper(
            this._inputField,
            SEARCH_WIDGET_RESULTS_SELECTOR,
            SEARCH_WIDGET_RESULT_ITEM_SELECTOR,
            true
        );

        this._registerEvents();
    }

    /**
     * Register events
     * @private
     */
    _registerEvents() {
        // add listener to the form's input event
        this._inputField.addEventListener(
            'input',
            Debouncer.debounce(this._handleInputEvent.bind(this), SEARCH_WIDGET_DELAY, false),
            {
                capture: true,
                passive: true
            }
        );

        // add click event listener to body
        let event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';
        document.body.addEventListener(event, this._onBodyClick.bind(this));
    }

    /**
     * Fire the XHR request if user inputs a search term
     * @private
     */
    _handleInputEvent() {
        let value = this._inputField.value;

        // stop search if minimum input value length has not been reached
        if (value.length < SEARCH_WIDGET_MIN_CHARS) {
            // further clear possibly existing search results
            this._clearSearchResults();
            return;
        }

        this._search(value);
    }

    /**
     * Process the AJAX search and show results
     * @param {string} value
     * @private
     */
    _search(value) {
        let url = this._url + encodeURI(value);

        // init loading indicator
        let indicator = new InputLoadingIndicator(this._inputField);
        indicator.create();

        this._client.get(url, (response) => {
            // remove existing search results popover first
            this._clearSearchResults();

            // remove indicator
            indicator.remove();

            // attach search results to the DOM
            document.body.insertAdjacentHTML('beforeend', response);
        });

    }

    /**
     * Remove existing search results popover from DOM
     * @private
     */
    _clearSearchResults() {
        // reseet arrow navigation helper to enable form submit on enter
        this._navigationHelper.resetIterator();

        // remove all result popovers
        let results = document.querySelectorAll(SEARCH_WIDGET_RESULTS_SELECTOR);
        results.forEach(result => result.remove());
    }

    /**
     * Close/remove the search results from DOM if user
     * clicks outside the form or the results popover
     * @param {Event} e
     * @private
     */
    _onBodyClick(e) {
        // early return if click target is the search form or any of it's children
        if (e.target.closest(SEARCH_WIDGET_SELECTOR)) {
            return;
        }

        // early return if click target is the search result or any of it's children
        if (e.target.closest(SEARCH_WIDGET_RESULTS_SELECTOR)) {
            return;
        }
        // remove existing search results popover
        this._clearSearchResults();
    }

}