import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import Storage from 'src/helper/storage/storage.helper';

export default class CartWidgetPlugin extends Plugin {

    static options = {
        cartWidgetStorageKey: 'cart-widget-template',
    };

    init() {

        this._client = new HttpClient();

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
            const storedContent = Storage.getItem(this.options.cartWidgetStorageKey);
            if (storedContent) {
                this.el.innerHTML = storedContent;
            }
        }

        this.$emitter.publish('insertStoredContent');
    }

    /**
     * Fetch the current cart widget template by calling the api
     * and persist the response to the browser's session storage
     */
    fetch() {
        this._client.get(window.router['frontend.checkout.info'], (response) => {

            Storage.setItem(this.options.cartWidgetStorageKey, response);
            this.el.innerHTML = response;

            this.$emitter.publish('fetch', { response });
        });
    }
}
