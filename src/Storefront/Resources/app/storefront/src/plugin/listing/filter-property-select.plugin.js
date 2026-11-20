/*
 * @sw-package inventory
 */

import FilterMultiSelectPlugin from 'src/plugin/listing/filter-multi-select.plugin';
import deepmerge from 'deepmerge';

export default class FilterPropertySelectPlugin extends FilterMultiSelectPlugin {

    static options = deepmerge(FilterMultiSelectPlugin.options, {
        propertyName: '',
    });

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        const activeCheckboxes =
            this.el.querySelectorAll(`${this.options.checkboxSelector}:checked`);

        let labels = [];

        if (activeCheckboxes) {
            activeCheckboxes.forEach((checkbox) => {
                labels.push({
                    label: checkbox.dataset.label,
                    id: checkbox.id,
                    previewHex: checkbox.dataset.previewHex,
                    previewImageUrl: checkbox.dataset.previewImageUrl,
                });
            });
        } else {
            labels = [];
        }

        return labels;
    }

    /**
     * @public
     */
    refreshDisabledState(filter) {
        // Prevent disabling if propertyName is not set correctly
        if (this.options.propertyName === '') {
            return;
        }

        const activeItems = [];
        const properties = filter[this.options.name];
        const entities = properties.entities;

        if (!entities) {
            this.disableFilter();
            return;
        }

        const property = entities.find(entity => entity.translated.name === this.options.propertyName);
        if (property) {
            activeItems.push(...property.options);
        } else {
            this.disableFilter();
            return;
        }

        const actualValues = this.getValues();

        if (activeItems.length < 1 && actualValues.properties.length === 0) {
            this.disableFilter();
            return;
        } else {
            this.enableFilter();
        }

        /*
         * Pair with the backend's group-aware aggregation (issue #15812): even when this widget
         * has selections, sibling options must be re-evaluated because the backend reports them
         * as available when OR-within-group would yield results. _disableInactiveFilterOptions
         * already preserves currently-checked items, so this is safe.
         */
        this._disableInactiveFilterOptions(activeItems.map(entity => entity.id));
    }
}
