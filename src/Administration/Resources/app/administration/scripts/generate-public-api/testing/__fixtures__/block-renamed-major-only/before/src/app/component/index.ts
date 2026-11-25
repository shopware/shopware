/**
 * @sw-package framework
 * @private
 */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopware.Component.register('sw-example-field-set', () => import('src/app/component/form/sw-example-field-set/index'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
