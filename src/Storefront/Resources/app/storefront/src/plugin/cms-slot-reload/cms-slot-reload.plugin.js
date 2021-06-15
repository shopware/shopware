import Plugin from 'src/plugin-system/plugin.class';
import CmsSlotReloadService from 'src/plugin/cms-slot-reload/service/cms-slot-reload.service';
import FormSerializeUtil from 'src/utility/form/form-serialize.util';
import CmsSlotOptionValidatorHelper from 'src/plugin/cms-slot-reload/helper/cms-slot-option-validator.helper';
import Iterator from 'src/helper/iterator.helper';

export default class CmsSlotReloadPlugin extends Plugin {

    static options = {
        cmsUrl: window.router['frontend.cms.page'],
        navigationUrl: window.router['frontend.cms.navigation.page'],
        cmsPageId: false,
        navigationId: false,
        elements: [],
        events: [],
        updateHistory: false,
        hiddenParams: [],
    };

    init() {
        if (!CmsSlotOptionValidatorHelper.validate(this.options)) {
            return;
        }

        this._slotReloader = new CmsSlotReloadService();
        this._prevData = FormSerializeUtil.serializeJson(this.el);
        this._registerEvents();
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        Iterator.iterate(this.options.events, this._addReloadEvent.bind(this));
    }

    /**
     * adds an event to the element
     *
     * @param event
     * @private
     */
    _addReloadEvent(event) {
        const reloadCmsSlot = this._reloadCmsSlot.bind(this);
        this.el.removeEventListener(event, reloadCmsSlot);
        this.el.addEventListener(event, reloadCmsSlot);
    }

    /**
     * reloads a cms slot
     *
     * @param event
     * @private
     */
    _reloadCmsSlot(event) {
        event.preventDefault();
        const data = FormSerializeUtil.serializeJson(this.el);

        this.$emitter.publish('beforeReloadCmsSlot');

        this._slotReloader.reload(this.options, data, this._prevData);
    }
}
