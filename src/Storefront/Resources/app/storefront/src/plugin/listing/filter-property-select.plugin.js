/*
 * @package inventory
 */

import FilterMultiSelectPlugin from 'src/plugin/listing/filter-multi-select.plugin';
import Iterator from 'src/helper/iterator.helper';
import DomAccess from 'src/helper/dom-access.helper';
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
            DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);

        let labels = [];

        if (activeCheckboxes) {
            Iterator.iterate(activeCheckboxes, (checkbox) => {
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

        if (actualValues.properties.length > 0) {
            return;
        }

        this._disableInactiveFilterOptions(activeItems.map(entity => entity.id));
    }
}
