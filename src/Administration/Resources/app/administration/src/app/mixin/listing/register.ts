/**
 * @sw-package framework
 */
import * as ListingMixin from './index';

Shopware.Mixin.register('listing', ListingMixin.default);
Shopware._private.publish('mixins', 'listing', ListingMixin);
