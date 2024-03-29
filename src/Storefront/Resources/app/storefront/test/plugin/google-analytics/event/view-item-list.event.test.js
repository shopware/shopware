import ViewItemListEvent from 'src/plugin/google-analytics/events/view-item-list.event';
import Feature from 'src/helper/feature.helper.js';

describe('plugin/google-analytics/events/view-item-list.event', () => {

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="cms-element-product-listing-wrapper">
                <div 
                    class="product-box" 
                    data-product-information='{ "id": "77b60eac624e41f58b19f4b003672b0c", "name": "Airbus A320neo" }'
                >
                    <h2 class="cart-title">Airbus A320neo</h2>
                </div>
                <div 
                    class="product-box" 
                    data-product-information='{ "id": "4899394fdc4b4ca885c260a53d3d8529", "name": "Boeing 777 300ER" }'
                >
                    <h2 class="cart-title">Boeing 777 300ER</h2>
                </div>
                <div 
                    class="product-box" 
                    data-product-information='{ "id": "0155277de46a4494b588e4ffc5f58e9e", "name": "Embraer 190" }'
                >
                    <h2 class="cart-title">Embraer 190</h2>
                </div>
            </div>
        `;

        window.gtag = jest.fn();

        /** @deprecated tag:v6.7.0 - Feature flag mock will be removed. */
        window.Feature = Feature;

        /** @deprecated tag:v6.7.0 - Feature flag mock will be removed. */
        window.Feature.init({ 'v6.7.0.0': true });
    });

    test('event is supported when listing wrapper is in the HTML', () => {
        expect(new ViewItemListEvent().supports()).toBe(true);
    });

    test('prepares the event data for gtag using data-attribute from the HTML', () => {
        new ViewItemListEvent().execute();

        // Verify gtag event is called with correct data from the HTML data-attribute
        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item_list', {
            'items': [
                { id: '77b60eac624e41f58b19f4b003672b0c', name: 'Airbus A320neo' },
                { id: '4899394fdc4b4ca885c260a53d3d8529', name: 'Boeing 777 300ER' },
                { id: '0155277de46a4494b588e4ffc5f58e9e', name: 'Embraer 190' },
            ],
        });
    });

    test('event has empty data when no product items are found', () => {
        document.body.innerHTML = `
            <div class="cms-element-product-listing-wrapper">
                <!-- No product items -->
            </div>
        `;

        new ViewItemListEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item_list', {
            'items': undefined,
        });
    });

    /** @deprecated tag:v6.7.0 - Test case for the deprecated implementation with hidden inputs. Will be removed. */
    test('prepares the event data for gtag using hidden input fields', () => {
        document.body.innerHTML = `
            <div class="cms-element-product-listing-wrapper">
                <div 
                    class="product-box" 
                    data-product-information='{ "id": "77b60eac624e41f58b19f4b003672b0c", "name": "Airbus A320neo" }'
                >
                    <h2 class="cart-title">Airbus A320neo</h2>
                    <input type="hidden" name="product-id" value="77b60eac624e41f58b19f4b003672b0c">
                    <input type="hidden" name="product-name" value="Airbus A320neo">
                </div>
                <div 
                    class="product-box" 
                    data-product-information='{ "id": "4899394fdc4b4ca885c260a53d3d8529", "name": "Boeing 777 300ER" }'
                >
                    <h2 class="cart-title">Boeing 777 300ER</h2>
                    <input type="hidden" name="product-id" value="4899394fdc4b4ca885c260a53d3d8529">
                    <input type="hidden" name="product-name" value="Boeing 777 300ER">
                </div>
                <div 
                    class="product-box" 
                    data-product-information='{ "id": "0155277de46a4494b588e4ffc5f58e9e", "name": "Embraer 190" }'
                >
                    <h2 class="cart-title">Embraer 190</h2>
                    <input type="hidden" name="product-id" value="0155277de46a4494b588e4ffc5f58e9e">
                    <input type="hidden" name="product-name" value="Embraer 190">
                </div>
            </div>
        `;

        window.Feature.init({ 'v6.7.0.0': false });

        new ViewItemListEvent().execute();

        // Verify gtag event is called with correct data from the HTML
        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item_list', {
            'items': [
                { id: '77b60eac624e41f58b19f4b003672b0c', name: 'Airbus A320neo' },
                { id: '4899394fdc4b4ca885c260a53d3d8529', name: 'Boeing 777 300ER' },
                { id: '0155277de46a4494b588e4ffc5f58e9e', name: 'Embraer 190' },
            ],
        });
    });
});
