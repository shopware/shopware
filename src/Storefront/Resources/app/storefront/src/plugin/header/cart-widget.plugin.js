import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import Storage from 'src/helper/storage/storage.helper';

export default class CartWidgetPlugin extends Plugin {

    static options = {
        cartWidgetStorageKey: 'cart-widget-template',
        emptyCartWidgetStorageKey: 'empty-cart-widget',
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
        // the page is initially always loaded with an empty cart
        // save the empty cart widget, to reuse it when the cart is emptied
        Storage.setItem(this.options.emptyCartWidgetStorageKey, this.el.innerHTML);

        const storedContent = Storage.getItem(this.options.cartWidgetStorageKey);
        if (storedContent) {
            this.el.innerHTML = storedContent;
        }

        this.$emitter.publish('insertStoredContent');
    }

    /**
     * Fetch the current cart widget template by calling the api
     * and persist the response to the browser's session storage
     */
    fetch() {
        this._client.get(window.router['frontend.checkout.info'], (content, response) => {
            if (response.status >= 500) {
                return;
            }

            if (response.status === 204) {
                Storage.removeItem(this.options.cartWidgetStorageKey);
                const emptyCartWidget = Storage.getItem(this.options.emptyCartWidgetStorageKey);
                if (emptyCartWidget) {
                    this.el.innerHTML = emptyCartWidget;
                }

                return;
            }

            Storage.setItem(this.options.cartWidgetStorageKey, content);
            this.el.innerHTML = content;

            this.$emitter.publish('fetch', { content });
        });
    }
}
