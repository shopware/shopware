import ListingPlugin from 'src/plugin/listing/listing.plugin';
import Feature from 'src/helper/feature.helper.js';

const template = `
    <div class="cms-element-product-listing-wrapper" data-listing-pagination="true">
        <!-- Pagination -->
        <nav aria-label="pagination" class="pagination-nav" data-pagination-position="top">
            <ul class="pagination">
                <li class="page-item page-first"><a href="#" class="page-link" data-page="1" data-focus-id="first">First</a></li>
                <li class="page-item page-prev"><a href="#" class="page-link" data-page="1" data-focus-id="prev">Prev</a></li>
                <li class="page-item"><a href="#" class="page-link" data-page="1" data-focus-id="1">1</a></li>
                <!-- active page -->
                <li class="page-item active"><a href="#" class="page-link" data-page="2" data-focus-id="2">2</a></li>
                <li class="page-item"><a href="#" class="page-link" data-page="3" data-focus-id="3">3</a></li>
                <li class="page-item"><a href="#" class="page-link" data-page="4" data-focus-id="4">4</a></li>
                <li class="page-item"><a href="#" class="page-link" data-page="5" data-focus-id="5">5</a></li>
                <li class="page-item page-next"><a href="#" class="page-link" data-page="3" data-focus-id="next">Next</a></li>
                <li class="page-item page-last"><a href="#" class="page-link" data-page="42" data-focus-id="last">Last</a></li>
            </ul>
        </nav>

        <!-- Listing product results -->
        <div class="cms-element-product-listing">
            <div class="row cms-listing-row js-listing-wrapper">
                <div class="card product-box box-standard"></div>
                <div class="card product-box box-standard"></div>
                <div class="card product-box box-standard"></div>
                <div class="card product-box box-standard"></div>
            </div>
        </div>
    </div>
`;

describe('listing-pagination.plugin', () => {
    let listingPaginationPlugin;
    let changeListingSpy;
    let saveFocusSpy;
    let resumeFocusSpy;

    beforeEach(async () => {
        window.Feature = Feature;
        window.Feature.init({ 'ACCESSIBILITY_TWEAKS': true });

        // Import plugin class async because of feature toggles inside static options
        const { default: ListingPaginationPlugin }  = await import('src/plugin/listing/listing-pagination.plugin');

        document.body.innerHTML = template;
        const element = document.querySelector('[data-listing-pagination]');

        window.PluginManager.getPluginInstanceFromElement = () => {
            // Listing plugin is using the same element as the pagination plugin
            return new ListingPlugin(element);
        }

        window.PluginManager.initializePlugins = jest.fn();

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        changeListingSpy = jest.spyOn(ListingPlugin.prototype, 'changeListing');
        saveFocusSpy = jest.spyOn(ListingPaginationPlugin.prototype, '_saveFocusState');
        resumeFocusSpy = jest.spyOn(ListingPaginationPlugin.prototype, '_resumeFocusState');

        listingPaginationPlugin = new ListingPaginationPlugin(element);

        listingPaginationPlugin.listing.httpClient = {
            get: jest.fn((url, callback) => {
                callback(`
                <div class="cms-element-product-listing-wrapper" data-listing="true">
                    <div class="cms-element-product-listing">
                        <div class="row cms-listing-row js-listing-wrapper">
                            <div class="card product-box box-standard"></div>
                            <div class="card product-box box-standard"></div>
                        </div>
                    </div>
                </div>
                `);
            }),
        }
    });

    test('plugin instance is created', () => {
        expect(typeof listingPaginationPlugin).toBe('object');
    });

    test('attempts to change listing when clicking on pagination item', () => {
        const pageItem = document.querySelector('[data-page="3"]');
        const getValuesSpy = jest.spyOn(listingPaginationPlugin, 'getValues');

        // Click on page-item for page 3
        pageItem.dispatchEvent(new Event('click', { bubbles: true }));

        // Ensure correct page is communicated to listing plugin
        expect(listingPaginationPlugin.getValues).toReturnWith({ 'p': '3' });
        expect(getValuesSpy).toHaveBeenCalledTimes(1);
        expect(changeListingSpy).toHaveBeenCalledTimes(1);
    });

    test('tries to set the focus back to the pagination link when content changes after pagination', () => {
        const pageItem = document.querySelector('[data-page="4"]');

        // Click on page-item for page 4
        pageItem.dispatchEvent(new Event('click', { bubbles: true }));

        // Ensure the focusHandler tries to save the correct selector
        expect(saveFocusSpy).toHaveBeenCalledTimes(1);
        expect(window.focusHandler.saveFocusState).toHaveBeenCalledWith('listing-pagination', '[data-pagination-location="top"] [data-focus-id="4"]');

        // Ensure the focusHandler tries to resume the focus with the correct parameters
        expect(resumeFocusSpy).toHaveBeenCalledTimes(1);
        expect(window.focusHandler.resumeFocusState).toHaveBeenCalledWith('listing-pagination', { preventScroll: true });
    });
});
