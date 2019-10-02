import Plugin from 'src/script/plugin-system/plugin.class';

export default class FilterBasePlugin extends Plugin {

    static options = {
        parentFilterPanelSelector: '.cms-element-product-listing-wrapper',
    };

    _init() {
        super._init();

        this._validateMethods();

        const parentFilterPanelElement = document.querySelector(this.options.parentFilterPanelSelector);

        this.listing = window.PluginManager.getPluginInstanceFromElement(
            parentFilterPanelElement,
            'Listing'
        );

        this.listing.registerFilter(this);
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
