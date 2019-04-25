/*
import polyfills
 */
import 'src/script/helper/polyfill-loader.helper';

/*
import base requirements
 */
import 'bootstrap';
import jQuery from 'jquery';

/*
import styles
 */
import 'src/style/base.scss';

/*
import helpers
 */
import PluginManager from 'src/script/helper/plugin/plugin.manager';
import ViewportDetection from 'src/script/helper/viewport-detection.helper';

/*
import utils
 */
import ModalExtensionUtil from 'src/script/utility/modal-extension/modal-extension.util';

/*
import plugins
 */
// import SimplePlugin from 'src/script/plugin/_example/simple.plugin';
// import VanillaExtendPlugin from 'src/script/plugin/_example/vanilla-extended.plugin';
// import ExtendedPlugin from 'src/script/plugin/_example/extended.plugin';
// import OverriddenPlugin from 'src/script/plugin/_example/overridden.plugin';

import CartWidgetPlugin from 'src/script/plugin/header/cart-widget.plugin';
import SearchWidgetPlugin from 'src/script/plugin/header/search-widget/search-widget.plugin';
import AccountMenuPlugin from 'src/script/plugin/header/account-menu.plugin';
import OffCanvasCartPlugin from 'src/script/plugin/offcanvas-cart/offcanvas-cart.plugin';
import CookiePermissionPlugin from 'src/script/plugin/cookie-permission/cookie-permission.plugin';
import CollapseFooterColumnsPlugin from 'src/script/plugin/collapse/collapse-footer-columns.plugin';
import FlyoutMenuPlugin from 'src/script/plugin/main-menu/flyout-menu.plugin';
import OffcanvasMenuPlugin from 'src/script/plugin/main-menu/offcanvas-menu.plugin';
import FormValidationPlugin from 'src/script/plugin/forms/form-validation.plugin';
import FormSubmitLoaderPlugin from 'src/script/plugin/forms/from-submit-loader.plugin';
import FieldTogglePlugin from 'src/script/plugin/forms/field-toggle.plugin';
import OffCanvasTabsPlugin from 'src/script/plugin/offcanvas-tabs/offcanvas-tabs.plugin';
import ImageSliderPlugin from 'src/script/plugin/image-slider/image-slider.plugin';
import ScrollToInvalidFieldPlugin from 'src/script/plugin/forms/scroll-to-invalid-field.plugin';
import ZoomModalPlugin from 'src/script/plugin/zoom-modal/zoom-modal.plugin';
import MagnifierPlugin from 'src/script/plugin/magnifier/magnifier.plugin';
import ImageZoomPlugin from 'src/script/plugin/image-zoom/image-zoom.plugin';

/*
initialisation
*/
new ViewportDetection();
// Expose jQuery and plugin manager to the global window object
window.jQuery = jQuery;
window.$ = jQuery;

// Necessary for the webpack hot module reloading server
if (module.hot) {
    module.hot.accept();
}

/*
register plugins
*/

// example plugin (remove before release)
// PluginManager.register('Simple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.executePlugin('Simple', 'body');
// PluginManager.register('VanillaExtendSimple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.extend('VanillaExtendSimple', 'VanillaExtendSimple', VanillaExtendPlugin, 'body', { plugin: 'simple vanilla extend' });
//
// PluginManager.register('ExtendSimple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.extend('ExtendSimple', 'NewExtendSimple', ExtendedPlugin, 'body', { plugin: 'simple extend' });
//
// PluginManager.register('OverrideSimple', SimplePlugin, 'body', { plugin: 'simple' });
// PluginManager.extend('OverrideSimple', 'OverrideSimple', OverriddenPlugin, 'body', { plugin: 'simple override' });
// example plugin end (remove before release)


PluginManager.register('CookiePermission', CookiePermissionPlugin, '[data-cookie-permission]');
PluginManager.register('SearchWidget', SearchWidgetPlugin, '[data-search-form]');
PluginManager.register('CartWidget', CartWidgetPlugin, '[data-cart-widget]');
PluginManager.register('OffCanvasCart', OffCanvasCartPlugin, '[data-offcanvas-cart]');
PluginManager.register('CollapseFooterColumns', CollapseFooterColumnsPlugin, '[data-collapse-footer]');
PluginManager.register('FlyoutMenu', FlyoutMenuPlugin, '[data-flyout-menu]');
PluginManager.register('OffcanvasMenu', OffcanvasMenuPlugin, '[data-offcanvas-menu]');
PluginManager.register('FormValidation', FormValidationPlugin, '[data-form-validation]');
PluginManager.register('ScrollToInvalidField', ScrollToInvalidFieldPlugin, 'form');
PluginManager.register('FormSubmitLoader', FormSubmitLoaderPlugin, '[data-form-submit-loader]');
PluginManager.register('FieldToggle', FieldTogglePlugin, '[data-field-toggle]');
PluginManager.register('AccountMenu', AccountMenuPlugin, '[data-offcanvas-account-menu]');
PluginManager.register('OffCanvasTabs', OffCanvasTabsPlugin, '[data-offcanvas-tab]');
PluginManager.register('ImageSlider', ImageSliderPlugin, '[data-image-slider]');
PluginManager.register('ZoomModal', ZoomModalPlugin, '[data-zoom-modal]');
PluginManager.register('Magnifier', MagnifierPlugin, '[data-magnifier]');
PluginManager.register('ImageZoom', ImageZoomPlugin, '[data-image-zoom]');

/*
add configurations
*/
// // applicable via data-simple-plugin-config="myConfig"
// window.PluginConfigManager.add('SimplePlugin', 'myConfig', { some: 'options' });
// import ExtendedSimplePluginConfig from 'src/script/config/_example/extended-simple-plugin.config';
// window.PluginConfigManager.add('SimplePlugin', 'extendedConfig', ExtendedSimplePluginConfig);


/*
pages
 */
import 'src/script/page/account/profile.page';

/*
run plugins
*/
document.addEventListener('DOMContentLoaded', () => PluginManager.executePlugins(), false);

/*
run utils
*/
new ModalExtensionUtil();
