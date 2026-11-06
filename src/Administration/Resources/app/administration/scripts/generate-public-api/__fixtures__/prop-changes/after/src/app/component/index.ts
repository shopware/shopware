/**
 * @sw-package framework
 * @private
 */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopware.Component.register('sw-example-entity-select', () => import('src/app/component/form/sw-example-entity-select/index'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
