/**
 * @sw-package framework
 * @private
 */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopware.Component.register('sw-example-card', () => import('src/app/component/base/sw-example-card/index'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
