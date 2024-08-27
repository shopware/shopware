import Plugin from 'src/plugin-system/plugin.class';

export default class FormAjaxPaginationPlugin extends Plugin {

    /**
     * @type {{paginationItemSelector: string, pageInputSelector: string}}
     */
    static options = {
        paginationItemSelector: '.pagination .page-link',
        pageInputSelector: 'input[name="p"]',
    };

    init() {
        /**
         * @type {Element|null}
         * @private
         */
        this._pageInput = this.el.querySelector(FormAjaxPaginationPlugin.options.pageInputSelector);

        /**
         * @type {NodeListOf<Element>}
         * @private
         */
        this._pageItems = this.el.querySelectorAll(FormAjaxPaginationPlugin.options.paginationItemSelector);

        /**
         * @type {FormAjaxSubmitPlugin|undefined}
         * @private
         */
        this._ajaxFormSubmitInstance = window.PluginManager.getPluginInstanceFromElement(this.el, 'FormAjaxSubmit');

        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        this._registerPageItems();
        this._registerAjaxFormSubmit();
    }

    /**
     * @private
     */
    _registerPageItems() {
        if (!this._pageItems) {
            return;
        }

        this._pageItems.forEach((element) => {
            element.addEventListener('click', this._onClickPage.bind(this));
        });
    }

    /**
     * @private
     */
    _registerAjaxFormSubmit() {
        if (!this._ajaxFormSubmitInstance) {
            return;
        }

        this._ajaxFormSubmitInstance.$emitter.subscribe('onAfterAjaxSubmit', this._afterContentChange.bind(this));
    }

    /**
     * @param event
     * @private
     */
    _onClickPage(event) {
        event.preventDefault();

        if (!this._pageInput) {
            return;
        }

        const currentPage = event.currentTarget.dataset.page;
        this._pageInput.value = currentPage;
        this._pageInput.dispatchEvent(new Event('change', { bubbles: true }));

        window.focusHandler.saveFocusState('form-ajax-pagination', `[data-focus-id="${currentPage}"]`);
    }

    /**
     * @private
     */
    _afterContentChange() {
        window.focusHandler.resumeFocusState('form-ajax-pagination');
    }
}