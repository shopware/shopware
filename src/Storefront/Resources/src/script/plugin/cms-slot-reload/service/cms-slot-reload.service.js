import HttpClient from 'src/script/service/http-client.service';
import HistoryUtil from 'src/script/utility/history/history.util';
import ElementLoadingIndicatorUtil from 'src/script/utility/loading-indicator/element-loading-indicator.util';
import DomAccess from 'src/script/helper/dom-access.helper';
import querystring from 'query-string';
import CmsSlotOptionValidatorHelper from 'src/script/plugin/cms-slot-reload/helper/cms-slot-option-validator.helper';

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
        this._reload();
    }

    /**
     * reloads a cms slot from the history
     * @param history
     */
    reloadFromHistory(history) {
        this._options = history.state.options;
        this._data = querystring.parse(history.search);
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
        this._domParser = new DOMParser();
        this._client = new HttpClient(window.accessKey, window.contextToken);

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
            slots: Object.keys(this._options.slots),
        };

        if (this._data) {
            data = Object.assign({}, data, this._data);
        }

        this._updateHistory();
        this._createLoadingIndicators();
        this._client.abort();
        this._client.post(url, JSON.stringify(data), this._onLoaded.bind(this));
    }

    /**
     * creates loading indicators for each element
     *
     * @private
     */
    _createLoadingIndicators() {
        this._options.slots.forEach((selectors, slotId) => {
            const targetSlots = DomAccess.querySelectorAll(document, `[data-slot-id="${slotId}"]`);
            targetSlots.forEach(slot => {
                ElementLoadingIndicatorUtil.create(slot);
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

        const state = {
            cmsPageLoader: true,
            options: this._options,
        };

        if (!this._historyChanged) {
            HistoryUtil.replaceParams(this._prevData, state);
            this._historyChanged = true;
        }

        HistoryUtil.pushParams(this._data, state);
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
        const markup = this._createMarkupFromString(response);
        this._replaceElements(markup);
    }

    /**
     * returns a dom element parsed from the passed string
     *
     * @param {string} string
     *
     * @returns {HTMLElement}
     * @private
     */
    _createMarkupFromString(string) {
        return this._domParser.parseFromString(string, 'text/html');
    }

    /**
     * replace all elements from the target
     * @param {HTMLElement} src
     * @private
     */
    _replaceElements(src) {
        this._options.slots.forEach((selectors, slotId) => {
            const srcSlots = DomAccess.querySelectorAll(src, `[data-slot-id="${slotId}"]`);
            const targetSlots = DomAccess.querySelectorAll(document, `[data-slot-id="${slotId}"]`);

            srcSlots.forEach(slot => {
                selectors.forEach(selector => {
                    const srcEls = DomAccess.querySelectorAll(slot, selector);

                    targetSlots.forEach(slot => {
                        ElementLoadingIndicatorUtil.remove(slot);
                        const targetEls = DomAccess.querySelectorAll(slot, selector);
                        targetEls.forEach((el, key) => {
                            el.innerHTML = srcEls[key].innerHTML;
                        });
                    });
                });
            });
        });
    }
}
