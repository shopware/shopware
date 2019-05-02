import Plugin from 'src/script/helper/plugin/plugin.class';
import CmsSlotReloadService from 'src/script/plugin/cms-slot-reload/service/cms-slot-reload.service';
import FormSerializeUtil from 'src/script/utility/form/form-serialize.util';
import CmsSlotOptionValidatorHelper from 'src/script/plugin/cms-slot-reload/helper/cms-slot-option-validator.helper';

export default class CmsSlotReloadPlugin extends Plugin {

    static options = {
        cmsUrl: window.router['widgets.cms.page'],
        navigationUrl: window.router['widgets.cms.navigation.page'],
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
        this._prevData = FormSerializeUtil.serialize(this.el);
        this._registerEvents();
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        this.options.events.forEach(this._addReloadEvent.bind(this));
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
        const data = FormSerializeUtil.serialize(this.el);
        this._slotReloader.reload(this.options, data, this._prevData);
    }
}
