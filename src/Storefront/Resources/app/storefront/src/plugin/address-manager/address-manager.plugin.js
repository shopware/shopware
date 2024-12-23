import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';
import ButtonLoadingIndicatorUtil from 'src/utility/loading-indicator/button-loading-indicator.util';

const SHIPPING = 'shipping';
const BILLING = 'billing';

/**
 * @package checkout
 */
export default class AddressManagerPlugin extends Plugin {

    static options = {
        activeBillingAddressId: null,
        activeShippingAddressId: null,
        initialTab: null,
        addressModalDialogScrollableClass: 'modal-dialog-scrollable',
        addressModalDialogSelectorClass: 'modal-dialog-address',
        dropdownSelector: '.dropdown',
        addressEditFormSelector: '.address-manager-modal-address-form',
        shippingAddressTabSelector: '#shipping-address-tab',
        billingAddressTabSelector: '#billing-address-tab',
        shippingTabPaneSelector: '#shipping-address-tab-pane',
        billingTabPaneSelector: '#billing-address-tab-pane',
        addressManagerModalSelector: '.address-manager-modal',
        addressEditCancelSelector: '.address-form-create-cancel',
        addressEditSubmitSelector: '.address-form-create-submit',
        setDefaultAddressSelector: '.address-manager-modal-set-default-address',
        addressEditorFormSubmitSelector: '#address-manager-modal-address-form',
        currentShippingIdSelector: '.address-manager-modal-currentShippingId',
        currentBillingIdSelector: '.address-manager-modal-currentBillingId',
        addressSelectItemSelector: '.address-manager-select-address',
        addressManagerUrl: '/widgets/account/address-manager',
        addressSwitchUrl: '/account/address/switch',
    };

    init() {
        if (![SHIPPING, BILLING].includes(this.options.initialTab)) {
            console.warn(`[AddressManagerPlugin] options.initialTab was expected to be 'billing' or 'shipping', got '${this.options.initialTab}'`);
            this.options.initialTab = SHIPPING;
        }

        this.el.removeEventListener('click', this._getModal.bind(this));
        this.el.addEventListener('click', this._getModal.bind(this));

        this._client = new HttpClient();
        this._registerEvents();
    }

    /**
     * registers all needed event listeners
     *
     * @private
     */
    _registerEvents() {
        document.querySelectorAll(this.options.addressEditFormSelector).forEach(element => {
            element.addEventListener('click', this._onRenderAddressForm.bind(this, element));
        });

        document.querySelectorAll(this.options.setDefaultAddressSelector).forEach(element => {
            element.addEventListener('click', this._onChangeDefaultAddress.bind(this, element));
        });

        document.querySelectorAll(this.options.addressSelectItemSelector).forEach(element => {
            element.addEventListener('click', this._onSetRadioInputActive.bind(this, element));
        });

        document
            .querySelector(this.options.shippingAddressTabSelector)
            ?.addEventListener('click', this._onSwitchToShippingTab.bind(this));

        document
            .querySelector(this.options.billingAddressTabSelector)
            ?.addEventListener('click', this._onSwitchToBillingTab.bind(this));

        document
            .querySelector(this.options.addressEditorFormSubmitSelector)
            ?.addEventListener('submit', this._onSaveChanges.bind(this));

        document
            .querySelector(this.options.addressEditCancelSelector)
            ?.addEventListener('click', this._onEditAddressCancel.bind(this));
    }

    _getModal(event) {
        event.preventDefault();

        try {
            this._btnLoader = new ButtonLoadingIndicatorUtil(event.currentTarget);
            this._btnLoader.create();
        } catch (error) {
            console.warn('[AddressManagerPlugin] Unable to create loading indicator on button', error);
        }

        this._client.get(
            this.options.addressManagerUrl,
            (response) => {
                this._renderModal(response);
                this._registerEvents();
            }
        );
    }

    _renderModal(response) {
        const pseudoModal = new PseudoModalUtil(response);

        pseudoModal.open(() => this._btnLoader.remove(), 0);

        const modal = pseudoModal.getModal();
        modal.children[0].classList.add(
            this.options.addressModalDialogScrollableClass,
            this.options.addressModalDialogSelectorClass
        );

        if (this.options.initialTab === BILLING) {
            modal.querySelector(this.options.billingAddressTabSelector).checked = true;
            this._onSwitchToBillingTab();
        } else {
            modal.querySelector(this.options.shippingAddressTabSelector).checked = true;
        }

        window.PluginManager.initializePlugins();
    }

    _onSwitchToBillingTab() {
        document.querySelector(this.options.billingAddressTabSelector).checked = true;
        document.querySelector(this.options.shippingTabPaneSelector).classList.remove('show', 'active');
        document.querySelector(this.options.billingTabPaneSelector).classList.add('show', 'active');
    }

    _onSwitchToShippingTab() {
        document.querySelector(this.options.shippingAddressTabSelector).checked = true;
        document.querySelector(this.options.billingTabPaneSelector).classList.remove('show', 'active');
        document.querySelector(this.options.shippingTabPaneSelector).classList.add('show', 'active');
    }

    _onSaveChanges(event) {
        event.preventDefault();

        this._client.post(
            event.currentTarget.action,
            new FormData(event.target),
            (data, response)  => {
                if (response.status === 204) {
                    location.reload();
                } else {
                    this._replaceModalContent(data);
                }
            }
        );
    }

    /**
     * @param {HTMLElement} element
     * @param {Event} event
     */
    _onSetRadioInputActive(element, event){
        if (event.target.closest(this.options.dropdownSelector)) {
            return;
        }

        const id = element.querySelector('input[type="radio"]').value;
        const type = element.querySelector('input[type="radio"]')?.dataset?.addressType;

        if (!id || !type) {
            console.warn('[AddressManagerPlugin] Radio input is missing id or address type');
            return;
        }

        if (type === SHIPPING) {
            document.querySelector(this.options.currentShippingIdSelector).value = id;
        } else {
            document.querySelector(this.options.currentBillingIdSelector).value = id;
        }

        element.querySelector('input[type="radio"]').checked = true;
    }

    _onChangeDefaultAddress(element) {
        const id = element?.dataset?.addressId;
        const type = element?.dataset?.addressType;

        if (!id || !type) {
            console.warn('[AddressManagerPlugin] Default button is missing address id or type attribute');
            return;
        }

        this._client.post(
            this.options.addressSwitchUrl,
            JSON.stringify({id, type}),
            (data) => this._replaceModalContent(data, type)
        );
    }

    _onRenderAddressForm(element) {
        const id = element?.dataset?.addressId;
        const type = element?.dataset?.addressType;

        if (!type) {
            console.warn('[AddressManagerPlugin] Form is missing address type attribute');
            return;
        }

        this._client.post(
            `${this.options.addressManagerUrl}${id ? `/${id}` : ''}?type=${type}`,
            '',
            (data) => this._replaceModalContent(data)
        );
    }

    _onEditAddressCancel(event) {
        const type = event.currentTarget?.dataset?.addressType;

        this._client.get(
            this.options.addressManagerUrl,
            (data) => this._replaceModalContent(data, type)
        );
    }

    _replaceModalContent(data, type = SHIPPING) {
        const dom = new DOMParser()
            .parseFromString(data, 'text/html')
            .querySelector('body')
            .children;

        document
            .querySelector(this.options.addressManagerModalSelector)
            .parentElement
            .replaceChildren(...dom);

        if (type === BILLING) {
            this._onSwitchToBillingTab();
        }

        window.PluginManager.initializePlugins();

        this._registerEvents();
    }
}