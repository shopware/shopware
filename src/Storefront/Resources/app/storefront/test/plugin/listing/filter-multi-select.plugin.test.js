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
});
