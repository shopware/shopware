import Plugin from 'src/plugin-system/plugin.class';
import HistoryUtil from 'src/utility/history/history.util';
import CmsSlotReloadService from 'src/plugin/cms-slot-reload/service/cms-slot-reload.service';

export default class CmsSlotHistoryReload extends Plugin {

    init() {
        this._validateRegistration();
        this._slotReloader = new CmsSlotReloadService();
        this._registerEvents();
    }

    /**
     * validates that the plugin is only registered
     * on the document and therefore can exist only once
     *
     * @private
     */
    _validateRegistration() {
        if (this.el !== document) {
            throw new Error('This Plugin can only be registered on the document element.');
        }
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        HistoryUtil.listen(this._onHistoryChange.bind(this));
    }

    /**
     * callback when the browser history changed
     * @param {*} history
     * @param {*} action
     *
     * @private
     */
    _onHistoryChange(history, action) {
        if (action === 'POP') {
            if (history.state && history.state.cmsPageLoader) {
                this._reloadSlot(history);
            }
        }

        this.$emitter.publish('onHistoryChange', { history, action });
    }

    /**
     * reloads the slot from the browser history
     *
     * @param {*} history
     *
     * @private
     */
    _reloadSlot(history) {
        this._slotReloader.reloadFromHistory(history);

        this.$emitter.publish('reloadSlot', { history });
    }
}
