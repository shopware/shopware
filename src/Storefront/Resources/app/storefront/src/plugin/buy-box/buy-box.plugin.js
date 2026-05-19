import Plugin from 'src/plugin-system/plugin.class';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';
import ElementReplaceHelper from 'src/helper/element-replace.helper';

export default class BuyBoxPlugin extends Plugin {

    static options = {
        elementId: '',
        buyWidgetSelector: '.product-detail-buy',
    };

    /**
     * Plugin initializer
     *
     * @returns {void}
     */
    init() {
        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this._httpClient = new HttpClient();
        this._registerEvents();
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        document.$emitter.subscribe('updateBuyWidget', this._handleUpdateBuyWidget.bind(this));
    }

    /**
     * Update buy widget after switching product variant
     *
     * @private
     */
    _handleUpdateBuyWidget(event) {
        if (!event.detail || this.options.elementId !== event.detail.elementId) {
            return;
        }

        ElementLoadingIndicatorUtil.create(this.el);

        this._httpClient.get(`${event.detail.url}`, (response) => {
            ElementReplaceHelper.replaceFromMarkup(response, `${this.options.buyWidgetSelector}-${this.options.elementId}`);
            ElementLoadingIndicatorUtil.remove(this.el);

            window.PluginManager.initializePlugins();
        });
    }
}
