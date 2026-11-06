/**
 * @sw-package framework
 * @private
 */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopware.Component.register('sw-native-example', () => import('./base/sw-native-example'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
