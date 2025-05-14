import ListingLimitPlugin from 'src/plugin/listing/listing-limit.plugin';

const template = `
    <div class="limit" data-listing-limit="true">
        <select class="form-select w-auto" aria-label="Products per page">
            <option value="12">12</option>
            <option value="18">18</option>
            <option value="24" selected>24</option>
            <option value="48">48</option>
            <option value="96">96</option>
        </select>
    </div>
`;

describe('ListingLimitPlugin', () => {
    let filterLimitPlugin;
    let mockSelectElement;
    let changeListingSpy;
    let deregisterFilterSpy;

    beforeEach(() => {
        // Setup mock DOM element
        document.body.innerHTML = template;

        // Mock the instance call of the listing plugin
        window.PluginManager.getPluginInstanceFromElement = (element, pluginName) => {
            if (pluginName === 'Listing') {
                return new class MockListingPlugin {
                    registerFilter() {}
                    deregisterFilter() {}
                    changeListing() {}
                };
            }

            return {};
        };

        filterLimitPlugin = new ListingLimitPlugin(document.querySelector('[data-listing-limit="true"]'), {
            limit: null,
        });

        mockSelectElement = document.querySelector('select');
    });

    test('should initialize with default options (limit null)', () => {
        expect(filterLimitPlugin.options.limit).toBeNull();
    });

    test('init() should select dropdown and register events', () => {
        const registerEventsSpy = jest.spyOn(filterLimitPlugin, '_registerEvents');
        filterLimitPlugin.init();
        expect(filterLimitPlugin.select).toBe(mockSelectElement);
        expect(registerEventsSpy).toHaveBeenCalled();
    });

    test('_registerEvents() should attach change event listener', () => {
        const addEventListenerSpy = jest.spyOn(mockSelectElement, 'addEventListener');

        filterLimitPlugin.select = mockSelectElement;
        filterLimitPlugin._registerEvents();

        expect(addEventListenerSpy).toHaveBeenCalledWith(
            'change',
            expect.any(Function)
        );
    });

    test('onChangeLimit() should update limit and call changeListing', () => {
        changeListingSpy = jest.spyOn(filterLimitPlugin.listing, 'changeListing');
        const event = { target: { value: '16' } };

        filterLimitPlugin.options.limit = null;
        filterLimitPlugin.onChangeLimit(event);

        expect(filterLimitPlugin.options.limit).toBe('16');
        expect(changeListingSpy).toHaveBeenCalled();
    });

    test('reset() should be callable (even if empty)', () => {
        expect(() => filterLimitPlugin.reset()).not.toThrow();
    });

    test('resetAll() should be callable (even if empty)', () => {
        expect(() => filterLimitPlugin.resetAll()).not.toThrow();
    });

    test('getValues() should return empty object when limit is null', () => {
        filterLimitPlugin.options.limit = null;
        expect(filterLimitPlugin.getValues()).toEqual({});
    });

    test('getValues() should return limit when set', () => {
        filterLimitPlugin.options.limit = '24';
        expect(filterLimitPlugin.getValues()).toEqual({ limit: '24' });
    });

    test('afterContentChange() should call deregisterFilter', () => {
        deregisterFilterSpy = jest.spyOn(filterLimitPlugin.listing, 'deregisterFilter');

        filterLimitPlugin.afterContentChange();
        expect(deregisterFilterSpy).toHaveBeenCalled();
    });

    test('getLabels() should return an empty array', () => {
        expect(filterLimitPlugin.getLabels()).toEqual([]);
    });
});
