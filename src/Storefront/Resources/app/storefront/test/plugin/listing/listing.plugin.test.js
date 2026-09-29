import ListingPlugin from 'src/plugin/listing/listing.plugin';
import ListingPaginationPlugin from 'src/plugin/listing/listing-pagination.plugin';

describe('ListingPlugin tests', () => {
    let listingPlugin = undefined;
    const spyInit = jest.fn();
    const spyInitializePlugins = jest.fn();

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
        delete window.activeRoute;
        delete window.activeRouteParameters;
        if (window.router) {
            delete window.router['frontend.account.login.page'];
        }
        window.history.replaceState({}, '', '/');
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

    test('the initialize should not be called', () => {
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
                el: inDomFirst,
            },
            {
                el: inDomSecond,
            },
            {
                el: inDomThird,
            },
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
                el: outDomFirst,
            },
            {
                el: outDomSecond,
            },
            {
                el: outDomThird,
            },
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

    test('should not autoscroll to top because we are at the top', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () => Promise.resolve('Listing result HTML'),
            }),
        );

        jest.spyOn(listingPlugin, '_scrollTopOfListing');
        window.scrollTo = jest.fn();
        window.scrollY = 0;

        expect(listingPlugin._scrollTopOfListing).not.toHaveBeenCalled();

        listingPlugin._buildRequest();
        await new Promise(process.nextTick);

        expect(listingPlugin._scrollTopOfListing).toHaveBeenCalled();

        expect(window.scrollTo).not.toHaveBeenCalled();
    });

    test('should autoscroll to top with scrollOffset because we are not at the top', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () => Promise.resolve('Listing result HTML'),
            }),
        );

        jest.spyOn(listingPlugin, '_scrollTopOfListing');
        window.scrollTo = jest.fn();
        window.scrollY = 500;

        listingPlugin._cmsProductListingWrapper.getBoundingClientRect = () => ({
            top: -500,
        });

        expect(listingPlugin._scrollTopOfListing).not.toHaveBeenCalled();

        listingPlugin._buildRequest();
        await new Promise(process.nextTick);

        expect(listingPlugin._scrollTopOfListing).toHaveBeenCalled();

        expect(window.scrollTo).toHaveBeenCalledWith({
            behavior: 'smooth',
            top: listingPlugin.options.scrollOffset * -1,
        });
    });

    test('should autoscroll to top of cmsElementProductListingWrapper because we are not at the top', () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () => Promise.resolve('Listing result HTML'),
            }),
        );

        const distanceToTop = 250;

        jest.spyOn(listingPlugin, '_scrollTopOfListing');
        window.scrollTo = jest.fn();
        window.scrollY = 500;

        listingPlugin._cmsProductListingWrapper.getBoundingClientRect = () => ({
            top: -1 * distanceToTop,
        });

        expect(listingPlugin._scrollTopOfListing).not.toHaveBeenCalled();

        listingPlugin._buildRequest();

        expect(listingPlugin._scrollTopOfListing).toHaveBeenCalled();

        expect(window.scrollTo).toHaveBeenCalledWith({
            behavior: 'smooth',
            top: distanceToTop - listingPlugin.options.scrollOffset,
        });
    });

    test('do not push history state if pass false pushHistory parameter into changeListing', () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () => Promise.resolve('Listing result HTML'),
            }),
        );

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

    test('updates the aria-live section after product results have changed',async () => {
        // Mock listing ajax call returning updated results
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () => Promise.resolve(`
                <div class="cms-element-product-listing-wrapper" data-listing="true">
                    <div class="cms-element-product-listing">
                        <div class="row cms-listing-row js-listing-wrapper" data-aria-live-text="Showing 2 products.">
                            <div class="card product-box box-standard"></div>
                            <div class="card product-box box-standard"></div>
                        </div>
                    </div>
                </div>
                `),
            }),
        );

        listingPlugin.changeListing(true);
        await new Promise(process.nextTick);

        // Verify that the new product results contain the data attribute with the updated aria-live text
        expect(document.querySelector('.js-listing-wrapper').dataset.ariaLiveText).toBe('Showing 2 products.');

        // Verify that the aria-live text in the filter panel has been updated
        expect(document.querySelector('.filter-panel-aria-live').textContent).toBe('Showing 2 products.');
    });

    describe('_fetchValuesOfRegisteredFilters', () => {
        test('does not crash when multiple filters contribute the same scalar key', () => {
            const filterA = { getValues: () => ({ p: 1 }) };
            const filterB = { getValues: () => ({ p: 2 }) };

            listingPlugin._registry = [filterA, filterB];

            expect(() => listingPlugin._fetchValuesOfRegisteredFilters()).not.toThrow();

            const result = listingPlugin._fetchValuesOfRegisteredFilters();
            expect(result.p).toEqual([1, 2]);
        });

        test('normalizes scalars, arrays and objects into a single array per key', () => {
            const scalarFilter = { getValues: () => ({ manufacturer: 'abc' }) };
            const arrayFilter = { getValues: () => ({ manufacturer: ['def', 'ghi'] }) };
            const objectFilter = { getValues: () => ({ properties: { a: 'p1', b: 'p2' } }) };

            listingPlugin._registry = [scalarFilter, arrayFilter, objectFilter];

            const result = listingPlugin._fetchValuesOfRegisteredFilters();

            expect(result.manufacturer).toEqual(['abc', 'def', 'ghi']);
            expect(result.properties).toEqual(['p1', 'p2']);
        });

        test('skips filters without getValues and handles null values gracefully', () => {
            const brokenFilter = {};
            const nullReturning = { getValues: () => null };
            const withNullValue = { getValues: () => ({ manufacturer: null, rating: undefined }) };
            const validFilter = { getValues: () => ({ manufacturer: ['abc'] }) };

            listingPlugin._registry = [brokenFilter, nullReturning, withNullValue, validFilter];

            expect(() => listingPlugin._fetchValuesOfRegisteredFilters()).not.toThrow();

            const result = listingPlugin._fetchValuesOfRegisteredFilters();
            expect(result.manufacturer).toEqual(['abc']);
            expect(result.rating).toEqual([]);
        });

        test('drops empty values so they cannot leak into the query as a bare separator', () => {
            const uncheckedA = { getValues: () => ({ 'shipping-free': '' }) };
            const uncheckedB = { getValues: () => ({ 'shipping-free': '' }) };

            listingPlugin._registry = [uncheckedA, uncheckedB];

            const filters = listingPlugin._fetchValuesOfRegisteredFilters();

            expect(filters['shipping-free']).toEqual([]);
            expect(listingPlugin._mapFilters(filters)['shipping-free']).toBeUndefined();
        });

        test('skips filters whose getValues throws and continues with remaining filters', () => {
            const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
            const throwingFilter = { getValues: () => { throw new Error('boom'); } };
            const validFilter = { getValues: () => ({ manufacturer: ['abc'] }) };

            listingPlugin._registry = [throwingFilter, validFilter];

            expect(() => listingPlugin._fetchValuesOfRegisteredFilters()).not.toThrow();

            const result = listingPlugin._fetchValuesOfRegisteredFilters();
            expect(result.manufacturer).toEqual(['abc']);
            expect(consoleWarnSpy).toHaveBeenCalledWith(
                expect.stringContaining('Listing filter plugin threw from getValues()'),
                expect.any(Error),
            );

            consoleWarnSpy.mockRestore();
        });
    });

    describe('_mapFilters', () => {
        test('returns single-valued keys like `p` as scalars even when multiple values were collected', () => {
            const mapped = listingPlugin._mapFilters({
                p: [1, 2],
                order: ['name-asc', 'price-desc'],
                limit: [24, 48],
            });

            expect(mapped.p).toBe('2');
            expect(mapped.order).toBe('price-desc');
            expect(mapped.limit).toBe('48');
            expect(mapped.p).not.toContain('|');
            expect(mapped.order).not.toContain('|');
            expect(mapped.limit).not.toContain('|');
        });

        test('pipe-joins multi-valued filter keys like `manufacturer` and `properties`', () => {
            const mapped = listingPlugin._mapFilters({
                manufacturer: ['abc', 'def'],
                properties: ['p1', 'p2', 'p3'],
            });

            expect(mapped.manufacturer).toBe('abc|def');
            expect(mapped.properties).toBe('p1|p2|p3');
        });

        test('keeps scalar `p` as a string without pipe when only one value exists', () => {
            const filter = { getValues: () => ({ p: 2 }) };
            listingPlugin._registry = [filter];

            const mapped = listingPlugin._mapFilters(listingPlugin._fetchValuesOfRegisteredFilters());

            expect(mapped.p).toBe('2');
            expect(mapped.p).not.toContain('|');
        });

        test('produces a valid scalar `p` when multiple filters contribute the page param', () => {
            const filterA = { getValues: () => ({ p: 1 }) };
            const filterB = { getValues: () => ({ p: 2 }) };
            listingPlugin._registry = [filterA, filterB];

            const mapped = listingPlugin._mapFilters(listingPlugin._fetchValuesOfRegisteredFilters());

            expect(mapped.p).toBe('2');
            expect(mapped.p).not.toBe('1|2');
        });

        test('returns scalar backend filter params as single values', () => {
            const mapped = listingPlugin._mapFilters({
                rating: ['3', '4'],
                'shipping-free': ['1', '1'],
                'min-price': ['10', '20'],
                'max-price': ['50', '60'],
            });

            expect(mapped.rating).toBe('4');
            expect(mapped['shipping-free']).toBe('1');
            expect(mapped['min-price']).toBe('20');
            expect(mapped['max-price']).toBe('60');
        });

        test('omits empty and nullish values', () => {
            const mapped = listingPlugin._mapFilters({
                manufacturer: [],
                properties: null,
                rating: undefined,
                shippingFree: '',
                order: ['name-asc'],
            });

            expect(mapped.manufacturer).toBeUndefined();
            expect(mapped.properties).toBeUndefined();
            expect(mapped.rating).toBeUndefined();
            expect(mapped.shippingFree).toBeUndefined();
            expect(mapped.order).toBe('name-asc');
        });
    });

    test('lets the built-in pagination plugin win the `p` param regardless of registration order', () => {
        const paginationPlugin = Object.create(ListingPaginationPlugin.prototype);
        paginationPlugin.getValues = () => ({ p: 5 });
        const thirdPartyFilter = { getValues: () => ({ p: 2 }) };

        listingPlugin._registry = [paginationPlugin, thirdPartyFilter];

        const mapped = listingPlugin._mapFilters(listingPlugin._fetchValuesOfRegisteredFilters());

        expect(mapped.p).toBe('5');
    });

    test('does not break the listing update when a filter plugin throws from getLabels', () => {
        const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const throwingFilter = { getLabels: () => { throw new Error('boom'); } };
        const validFilter = { getLabels: () => [{ id: 'abc', label: 'ABC' }] };

        listingPlugin._registry = [throwingFilter, validFilter];

        expect(() => listingPlugin._buildLabels()).not.toThrow();
        expect(listingPlugin.activeFilterContainer.innerHTML).toContain('ABC');

        consoleWarnSpy.mockRestore();
    });

    test('builds the labels for the active filters and renders them inside the filter panel', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                text: () => Promise.resolve(`
                <div class="cms-element-product-listing-wrapper" data-listing="true">
                    <div class="cms-element-product-listing">
                        <div class="row cms-listing-row js-listing-wrapper" data-aria-live-text="Showing 2 products.">
                            <div class="card product-box box-standard"></div>
                            <div class="card product-box box-standard"></div>
                        </div>
                    </div>
                </div>
                `),
            }),
        );

        const MockBooleanFilter = {
            getLabels: () => [{ label: 'Free shipping', id: 'shipping-free' }],
            getValues: () => { return { 'shipping-free': '1' }; },
        };

        const MockMultiSelectFilter = {
            getLabels: () => [{ label: 'Balistreri-Johns', id: '0190da2684cb710aac3d3291a340b3e3' }, { label: 'Pommes Spezial', id: '0190da2684cb710aac3d32919db761bb' }],
            getValues: () => { return { 'manufacturer': ['0190da2684cb710aac3d3291a340b3e3', '0190da2684cb710aac3d32919db761bb'] }; },
        };

        // Register filters so that the labels can be built later
        listingPlugin.registerFilter(MockBooleanFilter);
        listingPlugin.registerFilter(MockMultiSelectFilter);

        listingPlugin.changeListing(true);
        await new Promise(process.nextTick);

        const activeFilterElements = document.querySelectorAll('.filter-panel-active-container .filter-active');

        // Verify active filters are generated inside the DOM with correct aria-labels
        expect(activeFilterElements[0].textContent).toMatch('Free shipping');
        expect(activeFilterElements[0].getAttribute('aria-label')).toBe('Remove filter: Free shipping');

        expect(activeFilterElements[1].textContent).toMatch('Balistreri-Johns');
        expect(activeFilterElements[1].getAttribute('aria-label')).toBe('Remove filter: Balistreri-Johns');

        expect(activeFilterElements[2].textContent).toMatch('Pommes Spezial');
        expect(activeFilterElements[2].getAttribute('aria-label')).toBe('Remove filter: Pommes Spezial');
    });

    test('redirects to login on forbidden listing request', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: false,
                status: 403,
                text: jest.fn(),
            }),
        );

        const renderResponseSpy = jest.spyOn(listingPlugin, 'renderResponse');
        const navigateToSpy = jest.spyOn(listingPlugin, '_navigateTo')
            .mockImplementation(() => {});

        window.router = {
            ...window.router,
            'frontend.account.login.page': 'http://localhost/account/login',
        };
        window.activeRoute = 'frontend.wishlist.page';
        window.activeRouteParameters = JSON.stringify({ wishlistId: 'test-wishlist' });

        listingPlugin._buildRequest(true, { p: '2', order: 'name-asc' });
        await new Promise(process.nextTick);

        expect(renderResponseSpy).not.toHaveBeenCalled();
        expect(navigateToSpy).toHaveBeenCalledTimes(1);

        const loginUrl = new URL(navigateToSpy.mock.calls[0][0]);
        const redirectParameters = JSON.parse(loginUrl.searchParams.get('redirectParameters'));

        expect(`${loginUrl.origin}${loginUrl.pathname}`).toBe('http://localhost/account/login');
        expect(loginUrl.searchParams.get('redirectTo')).toBe('frontend.wishlist.page');
        expect(redirectParameters).toEqual({
            wishlistId: 'test-wishlist',
            p: '2',
            order: 'name-asc',
        });
    });
});
