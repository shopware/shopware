/**
 * @sw-package framework
 */
import * as TranslateWithFallbackMixin from './index';

Shopware.Mixin.register('translate-with-fallback', TranslateWithFallbackMixin.default);
Shopware._private.publish('mixins', 'translate-with-fallback', TranslateWithFallbackMixin);
