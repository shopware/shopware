/* eslint-disable */
import ListingPlugin from 'src/plugin/listing/listing.plugin';

describe('ListingPlugin tests', () => {
    let listingPlugin = undefined;
    let spyInit = jest.fn();
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        // mock listing plugins
        const mockElement = document.createElement('div');
        listingPlugin = new ListingPlugin(mockElement);
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
});
