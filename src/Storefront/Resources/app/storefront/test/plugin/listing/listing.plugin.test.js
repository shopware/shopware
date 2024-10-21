/* eslint-disable */
import ListingPlugin from 'src/plugin/listing/listing.plugin';

describe('ListingPlugin tests', () => {
    let listingPlugin = undefined;
    let spyInit = jest.fn();
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        document.body.innerHTML = `
            <!-- Filter panel -->
            <div class="cms-element-sidebar-filter">
                <div class="filter-panel">
                    <div class="filter-panel-items-container" role="list" aria-label="Filter">
                    </div>
                    <div class="filter-panel-active-container"></div>
                    <div class="filter-panel-aria-live visually-hidden" aria-live="polite" aria-atomic="true"></div>
                </div>
            </div>

            <!-- Product results -->
            <div class="cms-element-product-listing-wrapper" data-listing="true">
                <div class="cms-element-product-listing">
                    <div class="row cms-listing-row js-listing-wrapper" data-aria-live-text="Showing 24 out of 1000 products.">
                        <div class="card product-box box-standard"></div>
                        <div class="card product-box box-standard"></div>
                        <div class="card product-box box-standard"></div>
                        <div class="card product-box box-standard"></div>
                    </div>
                </div>
            </div>
        `;

        // mock listing plugins
        listingPlugin = new ListingPlugin(document.querySelector('[data-listing="true"]'));
        listingPlugin._registry = [];

        // create spy elements
        listingPlugin.init = spyInit;
        window.PluginManager.initializePlugins = spyInitializePlugins;
    });

    afterEach(() => {
        listingPlugin = undefined;
        spyInit.mockClear();
        spyInitializePlugins.mockClear();
        window.PluginManager.initializePlugins = undefined;
    });

    test('listing plugin exists', () => {
        expect(typeof listingPlugin).toBe('object');
    });

    test('listing plugin has the function refreshRegistry', () => {
        expect(typeof listingPlugin.refreshRegistry).toBe('function');
    });

    test('the init function is not called', () => {
        expect(spyInit).not.toHaveBeenCalled();
    });

    test('refreshRegistry calls the init function', () => {
        listingPlugin.refreshRegistry();

        expect(spyInit).toHaveBeenCalled();
    });

    test('refreshRegistry calls the initializePlugins function', () => {
        expect(spyInitializePlugins).not.toHaveBeenCalled();
    });

    test('refreshRegistry calls the initializePlugins function', () => {
        listingPlugin.refreshRegistry();

        expect(spyInitializePlugins).toHaveBeenCalled();
    });

    test('the init is called before initalizePlugins', () => {
        listingPlugin.refreshRegistry();

        const initCallOrder = spyInit.mock.invocationCallOrder[0];
        const spyInitializePluginsCallOrder = spyInitializePlugins.mock.invocationCallOrder[0];

        expect(initCallOrder).toBeLessThan(spyInitializePluginsCallOrder);
    });

    test('initalizePlugins is called after', () => {
        listingPlugin.refreshRegistry();

        const spyInitializePluginsCallOrder = spyInitializePlugins.mock.invocationCallOrder[0];
        const initCallOrder = spyInit.mock.invocationCallOrder[0];

        expect(spyInitializePluginsCallOrder).toBeGreaterThan(initCallOrder);
    });

    test('refreshRegistry filters non visible elements', () => {
        // mock _registry elements which are visible in the dom
        const inDomFirst = document.createElement('div');
        inDomFirst.classList.add('first-in-dom');
        const inDomSecond = document.createElement('div');
        inDomSecond.classList.add('second-in-dom');
        const inDomThird = document.createElement('div');
        inDomThird.classList.add('third-in-dom');

        document.body.append(inDomFirst);
        document.body.append(inDomSecond);
        document.body.append(inDomThird);

        const elementsInDocument = [
            {
                el: inDomFirst
            },
            {
                el: inDomSecond
            },
            {
                el: inDomThird
            }
        ];

        // mock _registry elements which are not visible in the dom
        const outDomFirst = document.createElement('div');
        outDomFirst.classList.add('first-out-dom');
        const outDomSecond = document.createElement('div');
        outDomSecond.classList.add('second-out-dom');
        const outDomThird = document.createElement('div');
        outDomThird.classList.add('third-out-dom');

        const elementsOutsideDocument = [
            {
                el: outDomFirst
            },
            {
                el: outDomSecond
            },
            {
                el: outDomThird
            }
        ];

        // add elements to listing plugin
        listingPlugin._registry = [...elementsInDocument, ...elementsOutsideDocument];

        // filter the registry
        listingPlugin.refreshRegistry();

        // expect that there are the elements which are existing in the dom
        expect(listingPlugin._registry).toContain(elementsInDocument[0]);
        expect(listingPlugin._registry).toContain(elementsInDocument[1]);
        expect(listingPlugin._registry).toContain(elementsInDocument[2]);

        // expect no elements which are not existing in the dom
        expect(listingPlugin._registry).not.toContain(elementsOutsideDocument[0]);
        expect(listingPlugin._registry).not.toContain(elementsOutsideDocument[1]);
        expect(listingPlugin._registry).not.toContain(elementsOutsideDocument[2]);
    });

    test('should not autoscroll to top because we are at the top', () => {
        const mockElement = document.createElement('div');
        const cmsElementProductListingWrapper = document.createElement('div');
        cmsElementProductListingWrapper.classList.add('cms-element-product-listing-wrapper');

        document.body.append(cmsElementProductListingWrapper);

        listingPlugin = new ListingPlugin(mockElement);

        jest.spyOn(listingPlugin, '_scrollTopOfListing');
        window.scrollTo = jest.fn();
        window.scrollY = 0;

        expect(listingPlugin._scrollTopOfListing).not.toHaveBeenCalled();

        listingPlugin._buildRequest();

        expect(listingPlugin._scrollTopOfListing).toHaveBeenCalled();

        expect(window.scrollTo).not.toHaveBeenCalled();
    });

    test('should autoscroll to top with scrollOffset because we are not at the top', () => {
        const mockElement = document.createElement('div');
        const cmsElementProductListingWrapper = document.createElement('div');
        cmsElementProductListingWrapper.classList.add('cms-element-product-listing-wrapper');

        document.body.append(cmsElementProductListingWrapper);

        listingPlugin = new ListingPlugin(mockElement);

        jest.spyOn(listingPlugin, '_scrollTopOfListing');
        window.scrollTo = jest.fn();
        window.scrollY = 500;

        listingPlugin._cmsProductListingWrapper.getBoundingClientRect = () => ({
            top: -500
        })

        expect(listingPlugin._scrollTopOfListing).not.toHaveBeenCalled();

        listingPlugin._buildRequest();

        expect(listingPlugin._scrollTopOfListing).toHaveBeenCalled();

        expect(window.scrollTo).toHaveBeenCalledWith({
            "behavior": "smooth",
            "top": listingPlugin.options.scrollOffset * -1
        });
    });

    test('should autoscroll to top of cmsElementProductListingWrapper because we are not at the top', () => {
        const distanceToTop = 250;

        const mockElement = document.createElement('div');
        const cmsElementProductListingWrapper = document.createElement('div');
        cmsElementProductListingWrapper.classList.add('cms-element-product-listing-wrapper');

        document.body.append(cmsElementProductListingWrapper);

        listingPlugin = new ListingPlugin(mockElement);

        jest.spyOn(listingPlugin, '_scrollTopOfListing');
        window.scrollTo = jest.fn();
        window.scrollY = 500;

        listingPlugin._cmsProductListingWrapper.getBoundingClientRect = () => ({
            top: -1 * distanceToTop
        })

        expect(listingPlugin._scrollTopOfListing).not.toHaveBeenCalled();

        listingPlugin._buildRequest();

        expect(listingPlugin._scrollTopOfListing).toHaveBeenCalled();

        expect(window.scrollTo).toHaveBeenCalledWith({
            "behavior": "smooth",
            "top": distanceToTop - listingPlugin.options.scrollOffset
        });
    });

    test('do not push history state if pass false pushHitory parameter into changeListing', () => {
        const mockElement = document.createElement('div');
        const cmsElementProductListingWrapper = document.createElement('div');
        cmsElementProductListingWrapper.classList.add('cms-element-product-listing-wrapper');

        document.body.append(cmsElementProductListingWrapper);

        listingPlugin = new ListingPlugin(mockElement);

        jest.spyOn(listingPlugin, '_updateHistory');
        listingPlugin.changeListing(false);

        expect(listingPlugin._updateHistory).not.toHaveBeenCalled();

        listingPlugin.changeListing(true);

        expect(listingPlugin._updateHistory).toHaveBeenCalled();
    });

    test('_onWindowPopstate get called when browser back', () => {
        const url = new URL(window.location);
        url.searchParams.set('foo', 'bar');
        window.history.pushState({}, '', url);

        const mockElement = document.createElement('div');
        const cmsElementProductListingWrapper = document.createElement('div');
        cmsElementProductListingWrapper.classList.add('cms-element-product-listing-wrapper');

        document.body.append(cmsElementProductListingWrapper);

        const mockOnWindowPopstateCallback = jest.fn();

        jest.spyOn(ListingPlugin.prototype, '_onWindowPopstate').mockImplementation(mockOnWindowPopstateCallback);

        listingPlugin = new ListingPlugin(mockElement);

        const popStateEvent = new PopStateEvent('popstate', { state: {} });
        dispatchEvent(popStateEvent);

        expect(mockOnWindowPopstateCallback).toHaveBeenCalled();

        ListingPlugin.prototype._onWindowPopstate.mockRestore();
    });

    test('updates the aria-live section after product results have changed', () => {
        // Mock listing ajax call returning updated results
        listingPlugin.httpClient = {
            get: jest.fn((url, callback) => {
                callback(`
                <div class="cms-element-product-listing-wrapper" data-listing="true">
                    <div class="cms-element-product-listing">
                        <div class="row cms-listing-row js-listing-wrapper" data-aria-live-text="Showing 2 products.">
                            <div class="card product-box box-standard"></div>
                            <div class="card product-box box-standard"></div>
                        </div>
                    </div>
                </div>
                `);
            })
        }

        listingPlugin.changeListing(true);

        // Verify that the new product results contain the data attribute with the updated aria-live text
        expect(document.querySelector('.js-listing-wrapper').dataset.ariaLiveText).toBe('Showing 2 products.');

        // Verify that the aria-live text in the filter panel has been updated
        expect(document.querySelector('.filter-panel-aria-live').textContent).toBe('Showing 2 products.');
    });

    test('builds the labels for the active filters and renders them inside the filter panel', () => {
        listingPlugin.httpClient = {
            get: jest.fn((url, callback) => {
                callback(`
                <div class="cms-element-product-listing-wrapper" data-listing="true">
                    <div class="cms-element-product-listing">
                        <div class="row cms-listing-row js-listing-wrapper" data-aria-live-text="Showing 2 products.">
                            <div class="card product-box box-standard"></div>
                            <div class="card product-box box-standard"></div>
                        </div>
                    </div>
                </div>
                `);
            })
        }

        const MockBooleanFilter = {
            getLabels: () => [{ label: 'Free shipping', id: 'shipping-free' }],
            getValues: () => { return { 'shipping-free': '1' } }
        };

        const MockMultiSelectFilter = {
            getLabels: () => [{ label: 'Balistreri-Johns', id: '0190da2684cb710aac3d3291a340b3e3' }, { label: 'Pommes Spezial', id: '0190da2684cb710aac3d32919db761bb' }],
            getValues: () => { return { 'manufacturer': ['0190da2684cb710aac3d3291a340b3e3', '0190da2684cb710aac3d32919db761bb'] } }
        };

        // Register filters so that the labels can be built later
        listingPlugin.registerFilter(MockBooleanFilter);
        listingPlugin.registerFilter(MockMultiSelectFilter);

        listingPlugin.changeListing(true);

        const activeFilterElements = document.querySelectorAll('.filter-panel-active-container .filter-active');

        // Verify active filters are generated inside the DOM with correct aria-labels
        expect(activeFilterElements[0].querySelector('[aria-hidden="true"]').textContent).toBe('Free shipping');
        expect(activeFilterElements[0].querySelector('.filter-active-remove').getAttribute('aria-label')).toBe('Remove filter: Free shipping');

        expect(activeFilterElements[1].querySelector('[aria-hidden="true"]').textContent).toBe('Balistreri-Johns');
        expect(activeFilterElements[1].querySelector('.filter-active-remove').getAttribute('aria-label')).toBe('Remove filter: Balistreri-Johns');

        expect(activeFilterElements[2].querySelector('[aria-hidden="true"]').textContent).toBe('Pommes Spezial');
        expect(activeFilterElements[2].querySelector('.filter-active-remove').getAttribute('aria-label')).toBe('Remove filter: Pommes Spezial');
    });
});
