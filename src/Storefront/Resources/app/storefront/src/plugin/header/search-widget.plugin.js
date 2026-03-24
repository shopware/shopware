import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import Debouncer from 'src/helper/debouncer.helper';
import HttpClient from 'src/service/http-client.service';
import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';
import Iterator from 'src/helper/iterator.helper';

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
            this._inputField = DomAccess.querySelector(this.el, this.options.searchWidgetInputFieldSelector);
            this._submitButton = DomAccess.querySelector(this.el, this.options.searchWidgetButtonFieldSelector);
            this._closeButton = DomAccess.querySelector(this.el, this.options.searchWidgetCloseButtonSelector);
            this._url = DomAccess.getAttribute(this.el, this.options.searchWidgetUrlDataAttribute);
        } catch (e) {
            return;
        }

        this._client = new HttpClient();

        this.searchSuggestLinks = [];

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
        document.body.addEventListener('click', this._onBodyClick.bind(this));

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

        if (!this.searchSuggestLinks || !this.searchSuggestLinks.length) {
            return;
        }

        window.focusHandler.setFocus(this.searchSuggestLinks[0], { focusVisible: true });
    }

    /**
     * Handles the keydown event on the search suggestions list,
     * to move the focus up or down the list.
     *
     * @param {number} index
     * @param {Event} event
     * @private
     */
    _handleSearchItemKeyEvent(index, event) {
        if (event.key !== 'ArrowDown' &&
            event.key !== 'ArrowUp') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        if (event.key === 'ArrowDown') {
            this._moveFocusDown(index);
        }

        if (event.key === 'ArrowUp') {
            this._moveFocusUp(index);
        }
    }

    /**
     * Moves the focus up the search results list.
     *
     * @param {number} currentIndex
     * @private
     */
    _moveFocusUp(currentIndex) {
        if (currentIndex === 0) {
            // Focus back on the input field.
            window.focusHandler.setFocus(this._inputField, { focusVisible: true });
        } else {
            const previousItem = this.searchSuggestLinks[currentIndex - 1];
            window.focusHandler.setFocus(previousItem, { focusVisible: true });
        }
    }

    /**
     * Moves the focus down the search results list.
     *
     * @param {number} currentIndex
     * @private
     */
    _moveFocusDown(currentIndex) {
        if (currentIndex < this.searchSuggestLinks.length) {
            const nextItem = this.searchSuggestLinks[currentIndex + 1];
            window.focusHandler.setFocus(nextItem, { focusVisible: true });
        }
    }

    /**
     * Process the AJAX suggest and show results
     * @param {string} value
     * @private
     */
    _suggest(value) {
        const url = this._url + encodeURIComponent(value);
        this._client.abort();

        // init loading indicator
        const indicator = new LoadingIndicatorUtil(this._submitButton);
        indicator.create();

        this.$emitter.publish('beforeSearch');

        this._client.get(url, (response) => {
            // remove existing search results popover first
            this._clearSuggestResults();

            // remove indicator
            indicator.remove();

            // attach search results to the DOM
            const searchWidgetButtonField = this.el.querySelector(this.options.searchWidgetButtonFieldSelector);
            searchWidgetButtonField.insertAdjacentHTML('afterend', response);

            this._inputField.setAttribute('aria-expanded', 'true');

            const searchSuggest = document.querySelector(this.options.searchWidgetResultSelector);

            this.searchSuggestLinks = Array.from(DomAccess.getFocusableElements(searchSuggest));

            this.searchSuggestLinks.forEach((item, index) => {
                item.addEventListener('keydown', this._handleSearchItemKeyEvent.bind(this, index));
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
        Iterator.iterate(results, result => result.remove());

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
        this._toggleButton = DomAccess.querySelector(document, this.options.searchWidgetCollapseButtonSelector, false);

        if (!this._toggleButton) {
            return;
        }

        this._toggleButton.addEventListener('click', this._focusInput.bind(this));
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
