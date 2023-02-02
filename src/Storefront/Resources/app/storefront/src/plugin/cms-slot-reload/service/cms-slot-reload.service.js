import querystring from 'query-string';
import deepmerge from 'deepmerge';
import HttpClient from 'src/service/http-client.service';
import HistoryUtil from 'src/utility/history/history.util';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';
import DomAccess from 'src/helper/dom-access.helper';
import CmsSlotOptionValidatorHelper from 'src/plugin/cms-slot-reload/helper/cms-slot-option-validator.helper';
import Iterator from 'src/helper/iterator.helper';
import ElementReplaceHelper from 'src/helper/element-replace.helper';

export default class CmsSlotReloadService {

    /**
     * reloads a cms slot
     *
     * @param options
     * @param data
     * @param prevData
     */
    reload(options, data = {}, prevData = {}) {
        this._options = options;
        this._data = data;
        this._prevData = prevData;

        this._loadFromHistory = false;

        document.$emitter.publish('CmsSlot/beforeReload');

        this._reload();
    }

    /**
     * reloads a cms slot from the history
     * @param history
     */
    reloadFromHistory(history) {
        this._options = history.state.options;
        this._data = deepmerge(querystring.parse(history.search), history.state.hiddenParams);
        this._prevData = history.state.prevData;

        this._loadFromHistory = true;

        this._reload();
    }

    /**
     * internal method to reload the slot
     *
     * @private
     */
    _reload() {
        if (!CmsSlotOptionValidatorHelper.validate(this._options)) {
            return;
        }

        this._historyChanged = this._historyChanged || false;
        this._client = new HttpClient();

        this._requestSlot();
    }

    /**
     * fire of the slot request
     *
     * @private
     */
    _requestSlot() {
        const url = this._getUrl();

        let data = {
            slots: Object.keys(this._options.elements),
        };

        if (this._data) {
            data = Object.assign({}, data, this._data);
        }

        this._updateHistory();
        this._createLoadingIndicators();

        document.$emitter.publish('CmsSlot/beforeRequestSlot');

        this._client.abort();
        this._client.post(url, JSON.stringify(data), this._onLoaded.bind(this));
    }

    /**
     * creates loading indicators for each element
     *
     * @private
     */
    _createLoadingIndicators() {
        this._handleLoadingIndicators(ElementLoadingIndicatorUtil.create);

        document.$emitter.publish('CmsSlot/createLoadingIndicators');
    }

    /**
     * removes loading indicators for each element
     *
     * @private
     */
    _removeLoadingIndicators() {
        this._handleLoadingIndicators(ElementLoadingIndicatorUtil.remove);

        document.$emitter.publish('CmsSlot/removeLoadingIndicators');
    }

    /**
     * iterates over cms elements
     *
     * @private
     */
    _handleLoadingIndicators(callback) {
        Iterator.iterate(this._options.elements, (selectors, elementId) => {
            const targetElements = DomAccess.querySelectorAll(document, `[data-cms-element-id="${elementId}"]`);
            Iterator.iterate(targetElements, element => {
                callback(element);
            });
        });
    }

    /**
     * updates the browser history
     *
     * @private
     */
    _updateHistory() {
        if (this._loadFromHistory) {
            return;
        }

        if (!this._options.updateHistory) {
            return;
        }

        if (!this._historyChanged) {
            this._replaceInitialHistory();
        }

        const params = this._prepareParams(this._data);
        HistoryUtil.pushParams(params.visibleParams, {
            cmsPageLoader: true,
            options: this._options,
            hiddenParams: params.hiddenParams,
        });

        document.$emitter.publish('CmsSlot/updateHistory');
    }

    /**
     * if the history was never changed before
     * we need to replace the current history
     * with the current state
     *
     * @private
     */
    _replaceInitialHistory() {
        const params = this._prepareParams(this._prevData);
        HistoryUtil.replaceParams(params.visibleParams, {
            cmsPageLoader: true,
            options: this._options,
            hiddenParams: params.hiddenParams,
        });
        this._historyChanged = true;
    }

    /**
     * splits the parameters
     * into visible and hidden
     *
     * @param data
     * @returns {{visibleParams: {}, hiddenParams: {}}}
     * @private
     */
    _prepareParams(data) {
        const params = {
            visibleParams: {},
            hiddenParams: {},
        };

        Iterator.iterate(data, (value, name) => {
            if (this._options.hiddenParams.indexOf(name) !== -1) {
                params.hiddenParams[name] = value;
            } else {
                params.visibleParams[name] = value;
            }
        });

        return params;
    }

    /**
     * returns the correct url
     * depending on whether or not
     * a navigation cms page is called
     *
     * @returns {string}
     * @private
     */
    _getUrl() {
        if (this._options.navigationId) {
            return `${this._options.navigationUrl}/${this._options.navigationId}`;
        } else if (this._options.cmsPageId) {
            return `${this._options.cmsUrl}/${this._options.cmsPageId}`;
        }

        throw new Error('Couldn\'t build url!');
    }

    /**
     * callback when the cms request
     * is finished
     *
     * @param response
     * @private
     */
    _onLoaded(response) {
        const preparedSelectors = this._prepareSelectors();
        ElementReplaceHelper.replaceFromMarkup(response, preparedSelectors);
        window.PluginManager.initializePlugins();
        this._removeLoadingIndicators();

        document.$emitter.publish('CmsSlot/onLoaded', { response });
    }

    /**
     * @returns {Array}
     *
     * @private
     */
    _prepareSelectors() {
        const preparedSelectors = [];

        Iterator.iterate(this._options.elements, (selectors, id) => {
            selectors = selectors.join(',').split(',');

            Iterator.iterate(selectors, selector => {
                preparedSelectors.push(`[data-cms-element-id="${id}"] ${selector}`);
            });
        });

        return preparedSelectors;
    }
}
