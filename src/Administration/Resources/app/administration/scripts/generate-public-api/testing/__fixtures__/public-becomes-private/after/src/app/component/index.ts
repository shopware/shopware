/**
 * @sw-package framework
 * @private
 */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopware.Component.register('sw-example-filter', () => import('src/app/component/base/sw-example-filter/index'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
