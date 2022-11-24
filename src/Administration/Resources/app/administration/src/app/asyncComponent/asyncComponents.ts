/* @private */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopware.Component.register('sw-code-editor', () => import('src/app/asyncComponent/form/sw-code-editor'));
    Shopware.Component.register('sw-chart', () => import('src/app/asyncComponent/base/sw-chart'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
