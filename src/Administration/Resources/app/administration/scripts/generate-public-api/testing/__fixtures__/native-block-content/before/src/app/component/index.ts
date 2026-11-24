/**
 * @sw-package framework
 * @private
 */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopware.Component.register('sw-native-example', () => import('src/app/component/base/sw-native-example/index'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
