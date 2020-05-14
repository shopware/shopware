import OffCanvasPlugin from 'src/plugin/offcanvas/offcanvas.plugin';
import DomAccess from 'src/helper/dom-access.helper';

export default class OffCanvasFilterPlugin extends OffCanvasPlugin {

    init() {
        this._registerEventListeners();
    }

    /**
     * Register events to handle opening the Detail Filter OffCanvas
     * by clicking a defined trigger selector
     * @private
     */
    _registerEventListeners() {
        this.el.addEventListener('click', this._onClickOffCanvasFilter.bind(this));
    }

    _onCloseOffCanvas(event) {
        const oldChildNode = event.detail.offCanvasContent[0];
        const filterContent = document.querySelector('[data-offcanvas-filter-content="true"]');

        // move filter back to original place
        filterContent.innerHTML = oldChildNode.innerHTML;

        this.$emitter.unsubscribe('onCloseOffcanvas', this._onCloseOffCanvas.bind(this));
        window.PluginManager.getPluginInstances('Listing')[0].refreshRegistry();
    }

    /**
     * On clicking the trigger item the OffCanvas shall open and the current
     * filter content should be moved inside the OffCanvas.
     * @param {Event} event
     * @private
     */
    _onClickOffCanvasFilter(event) {
        event.preventDefault();
        const filterContent = DomAccess.querySelector(document, '[data-offcanvas-filter-content="true"]');

        this.open(filterContent.innerHTML, () => {}, 'bottom', true, true, 'offcanvas-filter');

        const filterPanel = DomAccess.querySelector(filterContent, '.filter-panel');
        if (filterPanel) filterPanel.remove();

        window.PluginManager.getPluginInstances('Listing')[0].refreshRegistry();
        this.$emitter.subscribe('onCloseOffcanvas', this._onCloseOffCanvas.bind(this));

        this.$emitter.publish('onClickOffCanvasFilter');
    }
}
