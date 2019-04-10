import Plugin from 'asset/script/helper/plugin/plugin.class';
import HttpClient from 'asset/script/service/http-client.service';
import DomAccess from 'asset/script/helper/dom-access.helper';

const CART_WIDGET_ITEM_SELECTOR = '*[data-cart-widget=true]';
const CART_WIDGET_STORAGE_KEY = 'cart-widget-template';

export default class CartWidgetPlugin extends Plugin {

    init() {
        CartWidgetPlugin.fetch();
    }

    /**
     * Fetch the current cart widget template by calling the sales-channel-api
     * and persist the response to the browser's session storage
     */
    static fetch() {
        const parent = DomAccess.querySelector(document, CART_WIDGET_ITEM_SELECTOR, false).parentElement;

        if (!parent) {
            return;
        }

        const client = new HttpClient(window.accessKey, window.contextToken);
        const storageExists = (window.sessionStorage instanceof Storage);

        if (storageExists) {
            parent.innerHTML = window.sessionStorage.getItem(CART_WIDGET_STORAGE_KEY) || parent.innerHTML;
        }

        client.get(window.router['widgets.checkout.info'], (response) => {
            if (storageExists) {
                // persist the fetched template in the storage
                window.sessionStorage.setItem(CART_WIDGET_STORAGE_KEY, response);
            }

            parent.innerHTML = response || parent.innerHTML;
        });

    }
}
