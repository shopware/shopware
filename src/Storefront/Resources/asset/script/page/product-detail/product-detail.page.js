import PluginManager from 'asset/script/helper/plugin/plugin.manager';
import OffCanvasTabsPlugin from 'asset/script/plugin/off-canvas-tabs/offcanvas-tabs.plugin';
import GallerySlider from 'asset/script/page/product-detail/gallery-slider.page';

/**
 * REGISTER PLUGINS
 */
PluginManager.register('offCanvasTabsPlugin', OffCanvasTabsPlugin, document);

// Product Detail - Gallery Slider
new GallerySlider();
