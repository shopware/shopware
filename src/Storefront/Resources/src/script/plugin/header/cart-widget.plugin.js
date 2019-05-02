import Plugin from 'src/script/helper/plugin/plugin.class';
import HttpClient from 'src/script/service/http-client.service';

const CART_WIDGET_STORAGE_KEY = 'cart-widget-template';

export default class CartWidgetPlugin extends Plugin {

    init() {

        this._client = new HttpClient(window.accessKey, window.contextToken);
        this._storageExists = (window.sessionStorage instanceof Storage);

        this.insertStoredContent();
        this.fetch();
    }

    /**
     * reads the persisted content
     * from the session cache an renders it
     * into the element
     */
    insertStoredContent() {
        if (this._storageExists) {
            const storedContent = window.sessionStorage.getItem(CART_WIDGET_STORAGE_KEY);
            if (storedContent) {
                this.el.innerHTML = storedContent;
            }
        }
    }

    /**
     * Fetch the current cart widget template by calling the api
     * and persist the response to the browser's session storage
     */
    fetch() {
        this._client.get(window.router['widgets.checkout.info'], (response) => {

            // try to persist the fetched template in the storage
            if (this._storageExists) {
                window.sessionStorage.setItem(CART_WIDGET_STORAGE_KEY, response);
            }

            this.el.innerHTML = response;
        });
    }
}
