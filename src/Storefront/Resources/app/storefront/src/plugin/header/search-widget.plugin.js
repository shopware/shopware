import Plugin from 'src/plugin-system/plugin.class';
import Debouncer from 'src/helper/debouncer.helper';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';
import ButtonLoadingIndicator from 'src/utility/loading-indicator/button-loading-indicator.util';
import DeviceDetection from 'src/helper/device-detection.helper';

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

        this._inputField.addEventListener('keydown', this._handleKeyEvent.bind(this));

        this.el.addEventListener('submit', this._handleSearchEvent.bind(this));

        // add click event listener to body
        const event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';
        document.body.addEventListener(event, this._onBodyClick.bind(this));

        // add click event for mobile search
        this._registerInputFocus();

        // add click event listener to close button
        this._closeButton.addEventListener('click', this._onCloseButtonClick.bind(this));
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
     * Handles the keydown event on the input field,
     * to focus into the search suggestions list.
     * 
     * @param {Event} event
     * @private
     */
    _handleKeyEvent(event) {
        if (event.key !== 'ArrowDown' || 
            this._inputField.value.trim() === '') {
            return;
        }

        event.preventDefault();

        const searchSuggest = document.querySelector(this.options.searchWidgetResultSelector);
        if (!searchSuggest) {
            return;
        }

        const searchSuggestItems = searchSuggest.querySelectorAll(this.options.searchWidgetResultItemSelector);
        const firstItem = searchSuggestItems[0];

        if (!firstItem) {
            return;
        }

        const firstAnchor = firstItem.querySelector('a');
        window.focusHandler.setFocus(firstAnchor, { focusVisible: true, preventScroll: true });
    }

    /**
     * Handles the keydown event on the search suggestions list,
     * to move the focus up or down the list.
     * 
     * @param {Event} event
     * @private
     */
    _handleSearchItemKeyEvent(event) {
        if (event.key !== 'ArrowDown' && 
            event.key !== 'ArrowUp') {
            return;
        }
 
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        if (event.key === 'ArrowDown') {
            this._moveFocusDown(event.target);
        }

        if (event.key === 'ArrowUp') {
            this._moveFocusUp(event.target);
        }
    }

    /**
     * Moves the focus up the search results list.
     * 
     * @param {EventTarget} eventTarget
     * @private
     */
    _moveFocusUp(eventTarget) {
        const listItem = eventTarget.closest(this.options.searchWidgetResultItemSelector);
        if (!listItem) {
            return;
        }

        const previousItem = listItem.previousElementSibling;

        if (!previousItem) {
            // Focus back on the input field.
            window.focusHandler.setFocus(this._inputField, { focusVisible: true, preventScroll: true });
        } else {
            const previousAnchor = previousItem.querySelector('a');

            window.focusHandler.setFocus(previousAnchor, { focusVisible: true, preventScroll: true });
        }
    }

    /**
     * Moves the focus down the search results list.
     * 
     * @param {EventTarget} eventTarget
     * @private
     */
    _moveFocusDown(eventTarget) {
        const listItem = eventTarget.closest(this.options.searchWidgetResultItemSelector);
        if (!listItem) {
            return;
        }

        const nextItem = listItem.nextElementSibling;

        if (nextItem) {
            const nextAnchor = nextItem.querySelector('a');

            window.focusHandler.setFocus(nextAnchor, { focusVisible: true, preventScroll: true });
        }
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
                const searchWidgetButtonField = this.el.querySelector(this.options.searchWidgetButtonFieldSelector);
                searchWidgetButtonField.insertAdjacentHTML('afterend', content);

                this._inputField.setAttribute('aria-expanded', 'true');

                const searchSuggest = document.querySelector(this.options.searchWidgetResultSelector);
                const searchSuggestItems = searchSuggest.querySelectorAll(this.options.searchWidgetResultItemSelector);

                searchSuggestItems.forEach(item => {
                    const anchor = item.querySelector('a');
                    anchor.addEventListener('keydown', this._handleSearchItemKeyEvent.bind(this));
                });

                this.$emitter.publish('afterSuggest');
            });
    }

    /**
     * Remove existing search results popover from DOM
     * @private
     */
    _clearSuggestResults() {
        // remove all result popovers
        const results = document.querySelectorAll(this.options.searchWidgetResultSelector);
        results.forEach(result => result.remove());

        this._inputField.setAttribute('aria-expanded', 'false');

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
        this._clearSuggestResults();
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
