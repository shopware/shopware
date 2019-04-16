import Plugin from 'asset/script/helper/plugin/plugin.class';
import DomAccess from 'asset/script/helper/dom-access.helper';
import Debouncer from 'asset/script/helper/debouncer.helper';
import HttpClient from 'asset/script/service/http-client.service';
import InputLoadingIndicator from 'asset/script/util/loading-indicator/input-loading-indicator.util';
import DeviceDetection from 'asset/script/helper/device-detection.helper';
import ArrowNavigationHelper from 'asset/script/plugin/header/search-widget/helper/arrow-navigation.helper';

const SEARCH_WIDGET_SELECTOR = '.js-search-form';
const SEARCH_WIDGET_RESULTS_SELECTOR = '.js-search-result';
const SEARCH_WIDGET_RESULT_ITEM_SELECTOR = '.js-result';
const SEARCH_WIDGET_INPUT_FIELD_SELECTOR = 'input[type=search]';
const SEARCH_WIDGET_URL_DATA_ATTRIBUTE = 'data-url';

const SEARCH_WIDGET_DELAY = 250;
const SEARCH_WIDGET_MIN_CHARS = 3;

export default class SearchWidgetPlugin extends Plugin {

    init() {
        try {
            this._inputField = DomAccess.querySelector(this.el, SEARCH_WIDGET_INPUT_FIELD_SELECTOR);
            this._url = DomAccess.getAttribute(this.el, SEARCH_WIDGET_URL_DATA_ATTRIBUTE);
        } catch (e) {
            return;
        }

        this._client = new HttpClient(window.accessKey, window.contextToken);

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
            Debouncer.debounce(this._handleInputEvent.bind(this), SEARCH_WIDGET_DELAY),
            {
                capture: true,
                passive: true
            }
        );

        // add click event listener to body
        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';
        document.body.addEventListener(event, this._onBodyClick.bind(this));
    }

    /**
     * Fire the XHR request if user inputs a search term
     * @private
     */
    _handleInputEvent() {
        const value = this._inputField.value;

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
        const url = this._url + encodeURI(value);

        // init loading indicator
        const indicator = new InputLoadingIndicator(this._inputField);
        indicator.create();

        this._client.get(url, (response) => {
            // remove existing search results popover first
            this._clearSearchResults();

            // remove indicator
            indicator.remove();

            // attach search results to the DOM
            this.el.insertAdjacentHTML('beforeend', response);
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
        const results = document.querySelectorAll(SEARCH_WIDGET_RESULTS_SELECTOR);
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
