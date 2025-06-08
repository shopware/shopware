import FilterRatingSelectPlugin from 'src/plugin/listing/filter-rating-select.plugin';
import template from './filter-rating-select.plugin.template.html';

describe('FilterRatingSelect tests', () => {
    let filterRatingSelectPlugin;

    beforeEach(() => {
        document.body.innerHTML = template;

        // Mock the instance call of the listing plugin
        window.PluginManager.getPluginInstanceFromElement = (element, pluginName) => {
            if (pluginName === 'Listing') {
                return new class MockListingPlugin {
                    registerFilter() {}
                    changeListing() {}
                };
            }

            return {};
        };

        filterRatingSelectPlugin = new FilterRatingSelectPlugin(document.querySelector('[data-filter-rating-select="true"]'), {
            name: 'rating',
            displayName: 'Rating min.',
            checkboxSelector: '.filter-rating-select-radio',
            listItemSelector: '.filter-rating-select-list-item',
            snippets: {
                ariaLabel: 'Filter by minimum rating',
                ariaLabelCount: '%stars% stars selected',
                ariaLabelCountSingular: '1 star selected',
            },
        });
    });

    afterEach(() => {
        filterRatingSelectPlugin = undefined;
        document.body.innerHTML = '';
    });

    test('filter rating plugin exists', () => {
        expect(typeof filterRatingSelectPlugin).toBe('object');
    });

    test('should return correct values depending on radio input state', () => {
        // Select 4 stars
        document.getElementById('rating-4').setAttribute('checked', 'checked');
        document.getElementById('rating-4').checked = true;
        expect(filterRatingSelectPlugin.getValues()).toEqual({ rating: '4' });

        // Select 1 star
        document.getElementById('rating-1').setAttribute('checked', 'checked');
        document.getElementById('rating-1').checked = true;
        expect(filterRatingSelectPlugin.getValues()).toEqual({ rating: '1' });
    });

    test('should render correct counter depending on current rating', () => {
        // Select 4 stars and verify counter and aria-label
        document.getElementById('rating-4').setAttribute('checked', 'checked');
        document.getElementById('rating-4').checked = true;

        filterRatingSelectPlugin.getValues();

        expect(document.querySelector('.filter-multi-select-count').textContent).toBe('(4/5)');
        expect(document.querySelector('.filter-panel-item-toggle').getAttribute('aria-label')).toBe('Filter by minimum rating (4 stars selected)');

        // Select 1 star and verify counter and aria-label
        document.getElementById('rating-1').setAttribute('checked', 'checked');
        document.getElementById('rating-1').checked = true;

        filterRatingSelectPlugin.getValues();

        expect(document.querySelector('.filter-multi-select-count').textContent).toBe('(1/5)');
        expect(document.querySelector('.filter-panel-item-toggle').getAttribute('aria-label')).toBe('Filter by minimum rating (1 star selected)');
    });

    test('should render no counter or when no current rating is defined', () => {
        filterRatingSelectPlugin.getValues();

        expect(document.querySelector('.filter-multi-select-count').textContent).toBe('');
        expect(document.querySelector('.filter-panel-item-toggle').getAttribute('aria-label')).toBe('Filter by minimum rating');
    });
});
