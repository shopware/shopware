/**
 * @jest-environment jsdom
 */

/* eslint-disable */
import FilterMultiSelectPlugin from 'src/plugin/listing/filter-multi-select.plugin';
import ListingPlugin from 'src/plugin/listing/listing.plugin';

describe('FilterMultiSelect tests', () => {
    let filterMultiSelectPlugin = undefined;

    beforeEach(() => {
        // create mocks
        window.csrf = {
            enabled: false
        };

        window.router = [];

        const mockElement = document.createElement('div');

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

        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPluginInstanceFromElement: () => {
                return new ListingPlugin(mockElement);
            },
            getPlugin: () => {
                return {
                    get: () => []
                };
            }
        };

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
});
