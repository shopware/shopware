import Plugin from 'src/plugin-system/plugin.class';
import Debouncer from 'src/helper/debouncer.helper';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';
import ButtonLoadingIndicator from 'src/utility/loading-indicator/button-loading-indicator.util';
import DeviceDetection from 'src/helper/device-detection.helper';
import ArrowNavigationHelper from 'src/helper/arrow-navigation.helper';

export default class SearchWidgetPlugin extends Plugin {

    static options = {
        searchWidgetSelector: '.js-search-form',
        searchWidgetResultSelector: '.js-search-result',
        searchWidgetResultItemSelector: '.js-result',
        searchWidgetInputFieldSelector: 'input[type=search]',
        searchWidgetButtonFieldSelector: 'button[type=submit]',
        searchWidgetUrlDataAttribute: 'data-url',
        searchWidgetCollapseButtonSelector: '.js-search-toggle-btn',
        searchWidgetCollapseClass: 'collapsed',
        searchWidgetCloseButtonSelector: '.js-search-close-btn',

        searchWidgetDelay: 250,
        searchWidgetMinChars: 3,
    };

    init() {
        try {
            this._inputField = this.el.querySelector(this.options.searchWidgetInputFieldSelector);
            this._submitButton = this.el.querySelector(this.options.searchWidgetButtonFieldSelector);
            this._closeButton = this.el.querySelector(this.options.searchWidgetCloseButtonSelector);
            this._url = this.el.getAttribute(this.options.searchWidgetUrlDataAttribute);
        } catch (e) {
            return;
        }

        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this._client = new HttpClient();

        // initialize the arrow navigation
        this._navigationHelper = new ArrowNavigationHelper(
            this._inputField,
            this.options.searchWidgetResultSelector,
            this.options.searchWidgetResultItemSelector,
            true,
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
            Debouncer.debounce(this._handleInputEvent.bind(this), this.options.searchWidgetDelay),
            {
                capture: true,
                passive: true,
            },
        );

        this.el.addEventListener('submit', this._handleSearchEvent.bind(this));

        // add click event listener to body
        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';
        document.body.addEventListener(event, this._onBodyClick.bind(this));

        // add click event for mobile search
        this._registerInputFocus();

        // add click event listener to close button
        this._closeButton.addEventListener('click', this._onCloseButtonClick.bind(this));

        // add focus event listener to close button
        this._closeButton.addEventListener('focus', () => {
            document.querySelector(this.options.searchWidgetResultSelector).classList.add('d-none');
        });
    }

    _handleSearchEvent(event) {
        const value = this._inputField.value.trim();

        // stop search if minimum input value length has not been reached
        if (value.length < this.options.searchWidgetMinChars) {
            event.preventDefault();
            event.stopPropagation();
        }
    }

    /**
     * Fire the XHR request if user inputs a search term
     * @private
     */
    _handleInputEvent() {
        const value = this._inputField.value.trim();

        // stop search if minimum input value length has not been reached
        if (value.length < this.options.searchWidgetMinChars) {
            // further clear possibly existing search results
            this._clearSuggestResults();
            return;
        }

        this._suggest(value);

        this.$emitter.publish('handleInputEvent', { value });
    }

    /**
     * Process the AJAX suggest and show results
     * @param {string} value
     * @private
     */
    _suggest(value) {
        const url = this._url + encodeURIComponent(value);

        // init loading indicator
        const indicator = new ButtonLoadingIndicator(this._submitButton);
        indicator.create();

        this.$emitter.publish('beforeSearch');

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(response => response.text())
            .then(content => {
                // remove existing search results popover first
                this._clearSuggestResults();

                // remove indicator
                indicator.remove();

                // attach search results to the DOM
                this.el.insertAdjacentHTML('beforeend', content);

                this.$emitter.publish('afterSuggest');
            });
    }

    /**
     * Remove existing search results popover from DOM
     * @private
     */
    _clearSuggestResults() {
        // reset arrow navigation helper to enable form submit on enter
        this._navigationHelper.resetIterator();

        // remove all result popovers
        const results = document.querySelectorAll(this.options.searchWidgetResultSelector);
        results.forEach(result => result.remove());

        this.$emitter.publish('clearSuggestResults');
    }

    /**
     * Close/remove the search results from DOM if user
     * clicks outside the form or the results popover
     * @param {Event} e
     * @private
     */
    _onBodyClick(e) {
        // early return if click target is the search form or any of it's children
        if (e.target.closest(this.options.searchWidgetSelector)) {
            return;
        }

        // early return if click target is the search result or any of it's children
        if (e.target.closest(this.options.searchWidgetResultSelector)) {
            return;
        }
        // remove existing search results popover
        this._clearSuggestResults();

        this.$emitter.publish('onBodyClick');
    }

    /**
     * Close the search results popover
     * @private
     */
    _onCloseButtonClick() {
        this._inputField.value = '';
        this._inputField.focus();
    }

    /**
     * When the suggestion is shown, trigger the focus on the input field
     * @private
     */
    _registerInputFocus() {
        this._toggleButton = document.querySelector(this.options.searchWidgetCollapseButtonSelector);

        if (!this._toggleButton) {
            console.warn(`Called selector '${this.options.searchWidgetCollapseButtonSelector}' for the search toggle button not found. Autofocus has been disabled on mobile.`);
            return;
        }

        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';
        this._toggleButton.addEventListener(event, () => {
            setTimeout(() => this._focusInput(), 0);
        });
    }

    /**
     * Sets the focus on the input field
     * @private
     */
    _focusInput() {
        if (this._toggleButton && !this._toggleButton.classList.contains(this.options.searchWidgetCollapseClass)) {
            this._toggleButton.blur(); // otherwise iOS won't focus the field.
            this._inputField.focus();
        }

        this.$emitter.publish('focusInput');
    }
}
