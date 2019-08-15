import Plugin from 'src/script/plugin-system/plugin.class';

export default class FilterBasePlugin extends Plugin {

    static options = {
        parentFilterPanelSelector: '.filter-panel',
    };

    _init() {
        super._init();

        this._validateMethods();

        const parentFilterPanelElement = this.el.closest(this.options.parentFilterPanelSelector);

        if (!parentFilterPanelElement) {
            throw new Error(`
                [${this._pluginName}] The filter panel element could not be found.
                Your filter element is probably not inside a filter panel.
            `);
        }

        this.filterPanel = window.PluginManager.getPluginInstanceFromElement(
            parentFilterPanelElement,
            'FilterPanel'
        );

        this.filterPanel.registerFilter(this);
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
