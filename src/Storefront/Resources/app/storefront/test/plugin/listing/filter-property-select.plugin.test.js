/* eslint-disable */
import FilterPropertySelectPlugin from 'src/plugin/listing/filter-property-select.plugin';
import ListingPlugin from 'src/plugin/listing/listing.plugin';

/**
 * Regression test for https://github.com/shopware/shopware/issues/15812
 *
 * Verifies that refreshDisabledState() re-evaluates per-option disabled state even when the
 * widget already has selections. The backend (PropertyListingFilterHandler) provides group-aware
 * activeItems so sibling options become available again after a cross-group selection is removed.
 */
describe('FilterPropertySelect — group-aware refresh', () => {
    let mockElement;
    let plugin;

    const linenId = 'linen-id';
    const silkId = 'silk-id';

    function setupDom({ checked }) {
        mockElement = document.createElement('div');

        const wrapper = document.createElement('div');
        wrapper.classList.add('cms-element-product-listing-wrapper');
        mockElement.appendChild(wrapper);

        const span = document.createElement('span');
        span.classList.add('filter-multi-select-count');
        mockElement.appendChild(span);

        const toggle = document.createElement('button');
        toggle.classList.add('filter-panel-item-toggle');
        mockElement.appendChild(toggle);

        for (const { id, checked: c } of [{ id: linenId, checked }, { id: silkId, checked: false }]) {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.classList.add('filter-multi-select-checkbox');
            checkbox.id = id;
            if (c) checkbox.checked = true;
            const li = document.createElement('li');
            li.classList.add('filter-multi-select-list-item');
            li.appendChild(checkbox);
            mockElement.appendChild(li);
        }

        document.body.appendChild(mockElement);

        window.PluginManager = window.PluginManager || {};
        window.PluginManager.getPluginInstanceFromElement = () => new ListingPlugin(mockElement);
    }

    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('silk re-enables when only linen is selected and backend reports silk as available', () => {
        // Scenario from issue #15812 after deselecting the cross-group option:
        // only linen (material) is selected. Backend's per-group aggregation includes silk.
        setupDom({ checked: true });

        plugin = new FilterPropertySelectPlugin(mockElement, {
            name: 'properties',
            propertyName: 'material',
            checkboxSelector: '.filter-multi-select-checkbox',
            mainFilterButtonSelector: '.filter-panel-item-toggle',
            countSelector: '.filter-multi-select-count',
            listItemSelector: '.filter-multi-select-list-item',
        });

        // Simulate initial "broken" state: silk was disabled by a previous refresh when both
        // linen and a color option were selected.
        const silkCheckbox = document.getElementById(silkId);
        plugin.disableOption(silkCheckbox);
        expect(silkCheckbox.disabled).toBe(true);

        // Backend response: per-group material aggregation returns both linen and silk.
        plugin.refreshDisabledState({
            properties: {
                entities: [
                    {
                        translated: { name: 'material' },
                        options: [
                            { id: linenId, translated: { name: 'linen' } },
                            { id: silkId, translated: { name: 'silk' } },
                        ],
                    },
                ],
            },
        });

        // Silk must be re-enabled after refresh — previously the early-return skipped this.
        expect(silkCheckbox.disabled).toBe(false);
        // Linen stays checked (preserved by _disableInactiveFilterOptions's checked-skip).
        expect(document.getElementById(linenId).checked).toBe(true);
    });

    test('silk stays disabled when backend reports only linen as available', () => {
        // Scenario where another group's selection (e.g. tan) constrains material options so
        // only linen is reachable. Silk must stay disabled.
        setupDom({ checked: true });

        plugin = new FilterPropertySelectPlugin(mockElement, {
            name: 'properties',
            propertyName: 'material',
            checkboxSelector: '.filter-multi-select-checkbox',
            mainFilterButtonSelector: '.filter-panel-item-toggle',
            countSelector: '.filter-multi-select-count',
            listItemSelector: '.filter-multi-select-list-item',
        });

        plugin.refreshDisabledState({
            properties: {
                entities: [
                    {
                        translated: { name: 'material' },
                        options: [
                            { id: linenId, translated: { name: 'linen' } },
                        ],
                    },
                ],
            },
        });

        expect(document.getElementById(silkId).disabled).toBe(true);
        expect(document.getElementById(linenId).checked).toBe(true);
    });
});
