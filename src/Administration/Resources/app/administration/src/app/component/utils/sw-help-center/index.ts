import template from './sw-help-center.html.twig';
import './sw-help-center.scss';

/**
 * @deprecated tag:v6.7.0 - Will be removed. Please use sw-help-center-v2 instead.
 *
 * @package admin
 *
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-help-center', {
    template,
});
