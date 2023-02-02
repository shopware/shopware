import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

export default class FilterBasePlugin extends Plugin {

    static options = {
        parentFilterPanelSelector: '.cms-element-product-listing-wrapper',
        dropdownSelector: '.filter-panel-item-dropdown',
    };

    _init() {
        super._init();

        this._validateMethods();

        const parentFilterPanelElement = DomAccess.querySelector(document, this.options.parentFilterPanelSelector);

        this.listing = window.PluginManager.getPluginInstanceFromElement(
            parentFilterPanelElement,
            'Listing'
        );

        this.listing.registerFilter(this);

        this._preventDropdownClose();
    }

    _preventDropdownClose() {
        const dropdownMenu = DomAccess.querySelector(this.el, this.options.dropdownSelector, false);

        if (!dropdownMenu) {
            return;
        }

        dropdownMenu.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    }

    _validateMethods() {
        if (typeof this.getValues !== 'function') {
            throw new Error(`[${this._pluginName}] Needs the method "getValues"'`);
        }

        if (typeof this.getLabels !== 'function') {
            throw new Error(`[${this._pluginName}] Needs the method "getLabels"'`);
        }

        if (typeof this.reset !== 'function') {
            throw new Error(`[${this._pluginName}] Needs the method "reset"'`);
        }

        if (typeof this.resetAll !== 'function') {
            throw new Error(`[${this._pluginName}] Needs the method "resetAll"'`);
        }
    }
}
