import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';
import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import Feature from 'src/helper/feature.helper';

export default class OffCanvasFilter extends Plugin {

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

        /** @deprecated tag:v6.6.0 - Selector "data-offcanvas-filter-content" is deprecated. Use "data-off-canvas-filter-content" instead */
        const filterContent = Feature.isActive('v6.6.0.0')
            ? document.querySelector('[data-off-canvas-filter-content="true"]')
            : document.querySelector('[data-offcanvas-filter-content="true"]');

        // move filter back to original place
        filterContent.innerHTML = oldChildNode.innerHTML;

        document.$emitter.unsubscribe('onCloseOffcanvas', this._onCloseOffCanvas.bind(this));
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

        /** @deprecated tag:v6.6.0 - Selector "data-offcanvas-filter-content" is deprecated. Use "data-off-canvas-filter-content" instead */
        const filterContent = Feature.isActive('v6.6.0.0')
            ? document.querySelector('[data-off-canvas-filter-content="true"]')
            : document.querySelector('[data-offcanvas-filter-content="true"]');

        if (!filterContent) {
            throw Error('There was no DOM element with the data attribute "data-offcanvas-filter-content".')
        }

        OffCanvas.open(
            filterContent.innerHTML,
            () => {},
            'bottom',
            true,
            OffCanvas.REMOVE_OFF_CANVAS_DELAY(),
            true,
            'offcanvas-filter'
        );

        const filterPanel = DomAccess.querySelector(filterContent, '.filter-panel');

        // move filter from original place to offcanvas
        filterPanel.remove();

        window.PluginManager.getPluginInstances('Listing')[0].refreshRegistry();
        document.$emitter.subscribe('onCloseOffcanvas', this._onCloseOffCanvas.bind(this));

        this.$emitter.publish('onClickOffCanvasFilter');
    }
}
