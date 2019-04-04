import PluginManager from 'asset/script/helper/plugin/plugin.manager';
import ZoomModalPlugin from 'asset/script/plugin/zoom-modal/zoom-modal.plugin';
import MagnifierPlugin from 'asset/script/plugin/magnifier/magnifier.plugin';

PluginManager.register('ZoomModal', ZoomModalPlugin, '[data-zoom-modal="true"]');
PluginManager.register('Magnifier', MagnifierPlugin, '[data-magnify-lens="true"]');
