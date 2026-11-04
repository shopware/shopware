/**
 * @sw-package framework
 */
import * as DiscardDetailPageChangesMixin from './index';

Shopware.Mixin.register('discard-detail-page-changes', DiscardDetailPageChangesMixin.default);
Shopware._private.publish('mixins', 'discard-detail-page-changes', DiscardDetailPageChangesMixin);
