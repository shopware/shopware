/* eslint-disable */
import FilterMultiSelectPlugin from 'src/plugin/listing/filter-multi-select.plugin';
import ListingPlugin from 'src/plugin/listing/listing.plugin';

let mockElement = null;

describe('FilterMultiSelect tests', () => {
    let filterMultiSelectPlugin = undefined;

    beforeEach(() => {
        // create mocks
        mockElement = document.createElement('div');

        const cmsElementProductListingWrapper = document.createElement('div');
        cmsElementProductListingWrapper.classList.add('cms-element-product-listing-wrapper');

        const mockElementSpan = document.createElement('span');
        mockElementSpan.classList.add('filter-multi-select-count');

        const mockElementInput = document.createElement('Input');
        mockElementInput.classList.add('filter-multi-select-checkbox');

        const mockElementButton = document.createElement('button');
        mockElementButton.classList.add('filter-panel-item-toggle');

        mockElement.appendChild(cmsElementProductListingWrapper);
        mockElement.appendChild(mockElementInput);
        mockElement.appendChild(mockElementButton);
        mockElement.appendChild(mockElementSpan);

        document.body.appendChild(mockElement);

        window.PluginManager.getPluginInstanceFromElement = () => {
            return new ListingPlugin(mockElement);
        }

        filterMultiSelectPlugin = new FilterMultiSelectPlugin(mockElement);
    });

    afterEach(() => {
        filterMultiSelectPlugin = undefined;
        document.body.innerHTML = '';
    });

    test('filter multi select plugin exists', () => {
        expect(typeof filterMultiSelectPlugin).toBe('object');
    });

    test('element state attribute is set when disableFilter get called', () => {
        const templateButton = document.querySelector('.filter-panel-item-toggle');

        expect(templateButton.getAttribute('disabled')).toBeNull();
        expect(templateButton.getAttribute('title')).toBeNull();
        expect(templateButton.classList.contains('disabled')).toBe(false);

        filterMultiSelectPlugin.disableFilter();

        expect(templateButton.getAttribute('disabled')).toBe('disabled');
        expect(templateButton.getAttribute('title')).toBe(
            filterMultiSelectPlugin.options.snippets.disabledFilterText
        );
        expect(templateButton.classList.contains('disabled')).toBe(true);
    });

    test('element state attribute is set when enableFilter get called', () => {
        const templateButton = document.querySelector('.filter-panel-item-toggle');
        templateButton.setAttribute('disabled', 'disabled');
        templateButton.setAttribute(
            'title',
            filterMultiSelectPlugin.options.snippets.disabledFilterText
        );
        templateButton.classList.add('disabled');

        expect(templateButton.getAttribute('disabled')).toBe('disabled');
        expect(templateButton.getAttribute('title')).toBe(
            filterMultiSelectPlugin.options.snippets.disabledFilterText
        );
        expect(templateButton.classList.contains('disabled')).toBe(true);

        filterMultiSelectPlugin.enableFilter();

        expect(templateButton.getAttribute('disabled')).toBeNull();
        expect(templateButton.getAttribute('title')).toBeNull();
        expect(templateButton.classList.contains('disabled')).toBe(false);
    });

    test('element selection property is updated correctly when setValuesFromUrl is called', () => {
        filterMultiSelectPlugin.selection = ['red', 'blue', 'yellow'];
        filterMultiSelectPlugin.options.name = 'color';

        filterMultiSelectPlugin.selection.forEach(color => {
            const mockCheckedElementInput = document.createElement('Input');
            mockCheckedElementInput.classList.add('filter-multi-select-checkbox');
            mockCheckedElementInput.setAttribute('id', color);
            mockCheckedElementInput.checked = true;

            mockElement.appendChild(mockCheckedElementInput);
        })

        const mockUncheckElementInput = document.createElement('Input');
        mockUncheckElementInput.classList.add('filter-multi-select-checkbox');
        mockUncheckElementInput.setAttribute('id', 'pink');

        mockElement.appendChild(mockUncheckElementInput);

        expect(mockUncheckElementInput.checked).toBe(false);

        filterMultiSelectPlugin.setValuesFromUrl({
            color: 'blue|pink'
        });

        expect(mockUncheckElementInput.checked).toBe(true);

        expect(document.getElementById('blue').checked).toBe(true);
        expect(document.getElementById('red').checked).toBe(false);
        expect(document.getElementById('yellow').checked).toBe(false);

        expect(filterMultiSelectPlugin.selection.sort()).toEqual(['blue', 'pink'].sort());
    });

    test('should render correct counter depending on current selection', () => {
        document.body.innerHTML = `
            <div class="filter-multi-select filter-multi-select-manufacturer filter-panel-item dropdown" role="listitem" data-filter-multi-select="true">
                <button class="filter-panel-item-toggle btn" aria-expanded="false" aria-label="Filter by manufacturer" data-bs-toggle="dropdown" data-boundary="viewport" data-bs-offset="0,8" aria-haspopup="true">
                    Manufacturer <span class="filter-multi-select-count"></span>
                </button>
    
                <div class="filter-multi-select-dropdown filter-panel-item-dropdown dropdown-menu" id="filter-manufacturer-1854714896">
                    <ul class="filter-multi-select-list" aria-label="Manufacturer">
                        <li class="filter-multi-select-list-item">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input filter-multi-select-checkbox" data-label="Balistreri-Johns" value="0190da2684cb710aac3d3291a340b3e3" id="0190da2684cb710aac3d3291a340b3e3">
                                <label class="filter-multi-select-item-label form-check-label" for="0190da2684cb710aac3d3291a340b3e3">Balistreri-Johns</label>
                            </div>
                        </li>
                        <li class="filter-multi-select-list-item">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input filter-multi-select-checkbox" data-label="Barrows, Dach and Bergnaum" value="0190da2684cb710aac3d32919db761bb" id="0190da2684cb710aac3d32919db761bb">
                                <label class="filter-multi-select-item-label form-check-label" for="0190da2684cb710aac3d32919db761bb">Barrows, Dach and Bergnaum</label>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="cms-element-product-listing-wrapper"></div>
        `;

        filterMultiSelectPlugin = new FilterMultiSelectPlugin(document.querySelector('[data-filter-multi-select="true"]'), {
            name: 'manufacturer',
            displayName: 'Manufacturer',
            snippets: {
                ariaLabel: 'Filter by manufacturer',
                ariaLabelCount: '%count% selected',
            }
        });

        // Select one item from the multi select filter
        document.getElementById('0190da2684cb710aac3d3291a340b3e3').setAttribute('checked', 'checked');
        document.getElementById('0190da2684cb710aac3d3291a340b3e3').checked = true;

        filterMultiSelectPlugin.getValues();

        // Verify counter and aria-label
        expect(document.querySelector('.filter-multi-select-count').textContent).toBe('(1)');
        expect(document.querySelector('.filter-panel-item-toggle').getAttribute('aria-label')).toBe('Filter by manufacturer (1 selected)');
    });
});
