import pluginManager from '../../helper/plugin.manager';
import OffCanvasTabs from './../../plugin/off-canvas-tabs/offcanvas-tabs.plugin';
import GallerySlider from './gallery-slider.page';

/**
 * REGISTER PLUGINS
 */
pluginManager.register('offCanvasTabsPlugin', {
    plugin: OffCanvasTabs,
    selector: document
});

// Product Detail - Gallery Slider
new GallerySlider();
