import AddressManagerPlugin from 'src/plugin/address-manager/address-manager.plugin';

/**
 * @package checkout
 */

describe('AddressManagerPlugin test', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        jest.useRealTimers();
    });

    test('plugin initializes', () => {
        const addressManager = create();

        expect(typeof addressManager).toBe('object');
        expect(addressManager).toBeInstanceOf(AddressManagerPlugin);
    });

    test('warning for incorrect initialTab', () => {
        const logSpy = jest.spyOn(global.console, 'warn');

        create('dummny');

        expect(logSpy).toHaveBeenCalled();
        expect(logSpy).toHaveBeenCalledWith('[AddressManagerPlugin] options.initialTab was expected to be \'billing\' or \'shipping\', got \'dummny\'');
    });

    test('initial Tab is shipping', () => {
        const addressManager = create();

        expect(addressManager.options.initialTab).toBe('shipping');
    });

    test('open address manager modal', async () => {
        create();

        const button = document.querySelector('.btn');

        button.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        jest.runAllTimers();

        expect(window.PluginManager.initializePlugins).toHaveBeenCalledTimes(1);
        expect(global.fetch).toHaveBeenCalledWith(
            '/widgets/account/address-manager',
            { headers : {'X-Requested-With': 'XMLHttpRequest'}}
        );
    });

    test('switch to billing', async () => {
        const addressManager = create();

        const button = document.querySelector('.btn');

        button.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const checkbox = document.querySelector('#billing-address-tab');
        const billingTab = document.querySelector('#billing-address-tab-pane');
        const shippingTab = document.querySelector('#shipping-address-tab-pane');

        expect(checkbox.checked).toBe(false);

        addressManager._onSwitchToBillingTab();
        await new Promise(process.nextTick);

        expect(shippingTab.classList).not.toContain('active', 'show');
        expect(checkbox.checked).toBe(true);
        expect(billingTab.classList).toContain('active', 'show');
    });

    test('open modal with billing tag', async () => {
        create('billing');

        const button = document.querySelector('.btn');

        button.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const checkbox = document.querySelector('.js-pseudo-modal #billing-address-tab');
        const billingTab = document.querySelector('.js-pseudo-modal #billing-address-tab-pane');
        const shippingTab = document.querySelector('.js-pseudo-modal #shipping-address-tab-pane');

        expect(shippingTab.classList).not.toContain('active', 'show');
        expect(checkbox.checked).toBe(true);
        expect(billingTab.classList).toContain('active', 'show');
    });

    test('select shipping address from list', async () => {
        create();

        const button = document.querySelector('.btn');

        button.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const addressItem = document.querySelector('.address-manager-select-address');

        addressItem.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const currentShippingId = document.querySelector('.address-manager-modal-currentShippingId');
        const currentBillingId = document.querySelector('.address-manager-modal-currentBillingId');
        const radioButton = addressItem.querySelector('input[type="radio"]');

        expect(currentShippingId.value).toBe('address-dummy-id');
        expect(currentBillingId.value).toBe('');
        expect(radioButton.checked).toBe(true);
    });

    test('select billing address from list', async () => {
        create('billing');

        const button = document.querySelector('.btn');

        button.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const addressItem = document.querySelector('#billing-address-tab-pane .address-manager-select-address');

        addressItem.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const currentBillingId = document.querySelector('.address-manager-modal-currentBillingId');
        const radioButton = addressItem.querySelector('input[type="radio"]');

        expect(currentBillingId.value).toBe('address-dummy-id');
        expect(radioButton.checked).toBe(true);
    });

    test('set default address', async () => {
        const logSpy = jest.spyOn(global.console, 'warn');
        create();

        const button = document.querySelector('.btn');

        button.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const defaultAddress = document.querySelector('.address-manager-modal-set-default-address');

        defaultAddress.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        expect(logSpy).not.toHaveBeenCalled();
        expect(global.fetch).toHaveBeenNthCalledWith(1,
            '/widgets/account/address-manager',
            {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            },
        );
        expect(global.fetch).toHaveBeenNthCalledWith(2,
            '/account/address/switch',
            {
                method: 'POST',
                body: '{"id":"address-dummy-id","type":"shipping"}',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
            },
        );
    });

    test('open address edit / create from', async () => {
        const logSpy = jest.spyOn(global.console, 'warn');
        create();

        const button = document.querySelector('.btn');

        button.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        const renderAddressButton = document.querySelector('.address-manager-modal-address-form');

        renderAddressButton.dispatchEvent(new Event('click', { bubbles: true }));
        await new Promise(process.nextTick);

        expect(logSpy).not.toHaveBeenCalled();
        expect(global.fetch).toHaveBeenNthCalledWith(1,
            '/widgets/account/address-manager',
            {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            },
        );
        expect(global.fetch).toHaveBeenNthCalledWith(2,
            '/widgets/account/address-manager?type=shipping',
            {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            },
        );
    });
});

function create(initialTab = 'shipping') {
    document.body.innerHTML = `
             <button class="btn" data-address-manager="true">Open address manager</button>
             
             <div class="js-pseudo-modal-template">
                <div class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content js-pseudo-modal-template-root-element">
                            <div class="modal-header only-close">
                                <h5 class="modal-title js-pseudo-modal-template-title-element"></h5>
                                <button type="button" class="modal-close close" data-dismiss="modal" aria-label="Close">x</button>
                            </div>
                            <div class="modal-body js-pseudo-modal-template-content-element">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

    const addressManagerTemplate = `
            <div class="js-pseudo-modal-template-root-element address-manager-modal">
               <input type="checkbox" name="" id="shipping-address-tab">
               <input type="checkbox" name="" id="billing-address-tab">
               
               <div id="shipping-address-tab-pane" class="show active">
                    <div data-address-id="address-dummy-id" data-address-type="shipping">
                         <div class="address-manager-select-address">
                             <input
                                type="radio"
                                name="shipping"
                                value="address-dummy-id"
                                id="shipping-address-dummy-id"
                                data-address-type="shipping"
                                class="form-check-input col-auto"
                            />
                            <div class="dropdown">
                                <div class="address-manager-modal-set-default-address" data-address-id="address-dummy-id" data-address-type="shipping"></div>
                            </div>
                        </div>
                    </div>
                    <button class="address-manager-modal-address-form" data-address-type="shipping"></button>
               </div>
               <div id="billing-address-tab-pane" class="">
                    <div data-address-id="address-dummy-id" data-address-type="billing">
                         <div class="address-manager-select-address">
                             <input
                                type="radio"
                                name="billing"
                                value="address-dummy-id"
                                id="billing-address-dummy-id"
                                data-address-type="billing"
                                class="form-check-input col-auto"
                            />
                        </div>
                    </div>
               </div>
               
               <input type="text" class="address-manager-modal-currentShippingId">
               <input type="text" class="address-manager-modal-currentBillingId">
            </div>
        `;

    const element = document.querySelector('.btn');

    window.focusHandler = {
        saveFocusState: jest.fn(),
        resumeFocusState: jest.fn(),
    };

    const addressManager = new AddressManagerPlugin(element, {
        url: '/widgets/account/address-manager',
        initialTab,
    });

    global.fetch = jest.fn(() =>
        Promise.resolve({
            text: () => Promise.resolve(addressManagerTemplate),
        })
    );

    window.PluginManager.initializePlugins = jest.fn(() => Promise.resolve());

    jest.useFakeTimers({ legacyFakeTimers: true });

    return addressManager;
}
