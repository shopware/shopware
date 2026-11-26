/**
 * @sw-package framework
 * @private
 */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopware.Component.register('sw-example-state-card', () => import('src/app/component/base/sw-example-state-card/index'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
