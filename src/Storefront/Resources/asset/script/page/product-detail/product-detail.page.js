import PluginManager from '../../helper/plugin/plugin.manager';
import OffCanvasTabsPlugin from './../../plugin/off-canvas-tabs/offcanvas-tabs.plugin';
import GallerySlider from './gallery-slider.page';

/**
 * REGISTER PLUGINS
 */
PluginManager.register('offCanvasTabsPlugin', OffCanvasTabsPlugin, document);

// Product Detail - Gallery Slider
new GallerySlider();
