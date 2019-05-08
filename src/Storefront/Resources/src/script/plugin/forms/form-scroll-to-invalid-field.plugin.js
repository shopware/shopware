import Plugin from 'src/script/helper/plugin/plugin.class';
import Debouncer from 'src/script/helper/debouncer.helper';
import Iterator from 'src/script/helper/iterator.helper';

/**
 * this plugin scrolls to invalid form fields
 * when the form is submitted
 */
export default class FormScrollToInvalidFieldPlugin extends Plugin {

    static options = {

        /**
         * debounce time for the scroll event
         */
        scrollDebounceTime: 75,

        /**
         * how much px the scrolling should be offset
         */
        scrollOffset: 15,
    };

    init() {
        this._getForm();

        if (!this._form) {
            throw new Error(`No form found for the plugin: ${this.constructor.name}`);
        }

        this._formFields = this._getFormFields();
        if (!this._formFields || this._formFields.length === 0) {
            return;
        }

        this._assignDebouncedOnScrollEvent();
        this._registerEvents();
    }

    /**
     * tries to get the closest form
     *
     * @returns {HTMLElement|boolean}
     * @private
     */
    _getForm() {
        if (this.el && this.el.nodeType === 'FORM') {
            this._form = this.el;
        } else {
            this._form = this.el.closest('form');
        }
    }

    /**
     * returns a list of all form fields
     * associated with the current form
     *
     * @return {*}
     * @private
     */
    _getFormFields() {
        const formFields = this._form.querySelectorAll('input, select, textarea');

        const id = this._form.id;
        if (!id) return formFields;

        const formFieldsById = document.querySelectorAll(`input[form="${id}"], select[form="${id}"], textarea[form="${id}"]`);
        if (!formFieldsById) return formFields;

        return FormScrollToInvalidFieldPlugin._mergeNodeList(formFields, formFieldsById);
    }

    /**
     * registers all needed events
     *
     * @private
     */
    _registerEvents() {
        Iterator.iterate(this._formFields, field => {
            field.addEventListener('invalid', this._onInvalid.bind(this), false);
        });

        document.addEventListener('scroll', this._debouncedOnScroll, false);
    }

    /**
     * debounce is required to ensure the callback gets executed when scrolling ends
     *
     * @return {Function}
     * @private
     */
    _assignDebouncedOnScrollEvent() {
        this._debouncedOnScroll = Debouncer.debounce(this._onScrollEnd.bind(this), this.options.scrollDebounceTime);
    }

    /**
     * gets called all invalid fields if the form got submitted
     *
     * @param event
     * @private
     */
    _onInvalid(event) {
        if (event.target._ignoreValidityEvent) {
            delete event.target._ignoreValidityEvent;
            return;
        }


        event.preventDefault();
        event.stopPropagation();

        if (this._firstInvalidElement) {
            return;
        }

        this._getFirstInvalidFormFields(event);
        this._scrollToInvalidFormFields();
    }

    /**
     * assigns the first invalid
     * element out of all form elements
     *
     * @param event
     * @private
     */
    _getFirstInvalidFormFields(event) {
        Iterator.iterate(this._formFields, field => {
            if (field === event.target) {
                this._firstInvalidElement = field;
            }
        });
    }

    /**
     * gets called when the
     * scroll animation has finished
     *
     * @private
     */
    _onScrollEnd() {
        if (this._firstInvalidElement) {
            this._firstInvalidElement._ignoreValidityEvent = true;
            this._firstInvalidElement.reportValidity(false);
            this._firstInvalidElement = false;
        }
    }

    /**
     * scrolls to the first invalid form field
     *
     * @private
     */
    _scrollToInvalidFormFields() {
        const rect = this._firstInvalidElement.getBoundingClientRect();
        const elementScrollOffset = rect.top + window.scrollY;
        const offset = elementScrollOffset - this.options.scrollOffset;

        // if the window is already scrolled to the right position
        // trigger the onScrollEnd callback instantly
        if (window.scrollY === offset) {
            this._debouncedOnScroll();
        } else {
            window.scrollTo({
                top: offset,
                behavior: 'smooth',
            });
        }
    }

    /**
     * merges two NodeLists together
     *
     * @param src
     * @param target
     * @return {any[]}
     * @private
     */
    static _mergeNodeList(src, target) {
        return [...Array.from(src), ...Array.from(target)];
    }
}
