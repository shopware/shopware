import FilterBooleanPlugin from 'src/plugin/listing/filter-boolean.plugin';

describe('FilterBoolean tests', () => {
    let filterBooleanPlugin;

    beforeEach(() => {
        document.body.innerHTML = `
        <div class="filter-boolean filter-panel-item" role="listitem" data-filter-boolean="true">
            <div class="form-check">
                <input type="checkbox" class="filter-boolean-input form-check-input" id="shipping-free" name="shipping-free">
                    <label for="shipping-free" class="filter-boolean-label custom-control-label">
                        <span class="filter-boolean-alt-text visually-hidden">Add filter: Free shipping</span>
                        <span aria-hidden="true">Free shipping</span>
                    </label>
            </div>
        </div>

        <div class="cms-element-product-listing-wrapper"></div>
        `;

        // Mock the instance call of the listing plugin
        window.PluginManager.getPluginInstanceFromElement = (element, pluginName) => {
            if (pluginName === 'Listing') {
                return new class MockListingPlugin {
                    registerFilter() {}
                };
            }

            return {};
        };

        filterBooleanPlugin = new FilterBooleanPlugin(document.querySelector('[data-filter-boolean="true"]'), {
            name: 'shipping-free',
            displayName: 'Free shipping',
            snippets: {
                altText: 'Add filter: Free shipping',
                altTextActive: 'Remove filter: Free shipping',
            },
        });
    });

    test('filter boolean plugin exists', () => {
        expect(typeof filterBooleanPlugin).toBe('object');
    });

    test('should return correct values depending on checkbox state', () => {
        document.querySelector('.filter-boolean-input').checked = true;
        expect(filterBooleanPlugin.getValues()).toEqual({ 'shipping-free': '1' });

        document.querySelector('.filter-boolean-input').checked = false;
        expect(filterBooleanPlugin.getValues()).toEqual({ 'shipping-free': '' });
    });

    test('should render the correct alt text depending on checkbox state', () => {
        document.querySelector('.filter-boolean-input').checked = true;
        filterBooleanPlugin._updateAltText();

        expect(document.querySelector('.filter-boolean-alt-text').textContent).toBe('Remove filter: Free shipping');

        document.querySelector('.filter-boolean-input').checked = false;
        filterBooleanPlugin._updateAltText();

        expect(document.querySelector('.filter-boolean-alt-text').textContent).toBe('Add filter: Free shipping');
    });
});
