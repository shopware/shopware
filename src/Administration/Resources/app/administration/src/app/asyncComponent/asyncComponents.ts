/**
 * @package admin
 */

/* @private */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations, max-len */
    Shopware.Component.register('sw-code-editor', () => import('src/app/asyncComponent/form/sw-code-editor'));
    Shopware.Component.register('sw-datepicker', () => import('src/app/asyncComponent/form/sw-datepicker'));
    Shopware.Component.register('sw-chart', () => import('src/app/asyncComponent/base/sw-chart'));

    Shopware.Component.register('sw-image-slider', () => import('src/app/asyncComponent/media/sw-image-slider'));
    Shopware.Component.register('sw-media-add-thumbnail-form', () => import('src/app/asyncComponent/media/sw-media-add-thumbnail-form'));
    Shopware.Component.register('sw-media-base-item', () => import('src/app/asyncComponent/media/sw-media-base-item'));
    // @ts-expect-error - the extended component is not a valid vue configuration
    Shopware.Component.extend('sw-media-compact-upload-v2', 'sw-media-upload-v2', () => import('src/app/asyncComponent/media/sw-media-compact-upload-v2'));
    Shopware.Component.register('sw-media-entity-mapper', () => import('src/app/asyncComponent/media/sw-media-entity-mapper'));
    Shopware.Component.register('sw-media-field', () => import('src/app/asyncComponent/media/sw-media-field'));
    Shopware.Component.register('sw-media-folder-content', () => import('src/app/asyncComponent/media/sw-media-folder-content'));
    Shopware.Component.register('sw-media-folder-item', () => import('src/app/asyncComponent/media/sw-media-folder-item'));
    Shopware.Component.register('sw-media-list-selection-item-v2', () => import('src/app/asyncComponent/media/sw-media-list-selection-item-v2'));
    Shopware.Component.register('sw-media-list-selection-v2', () => import('src/app/asyncComponent/media/sw-media-list-selection-v2'));
    Shopware.Component.register('sw-media-media-item', () => import('src/app/asyncComponent/media/sw-media-media-item'));
    Shopware.Component.register('sw-media-modal-delete', () => import('src/app/asyncComponent/media/sw-media-modal-delete'));
    Shopware.Component.register('sw-media-modal-folder-dissolve', () => import('src/app/asyncComponent/media/sw-media-modal-folder-dissolve'));
    Shopware.Component.register('sw-media-modal-folder-settings', () => import('src/app/asyncComponent/media/sw-media-modal-folder-settings'));
    Shopware.Component.register('sw-media-modal-move', () => import('src/app/asyncComponent/media/sw-media-modal-move'));
    Shopware.Component.register('sw-media-modal-replace', () => import('src/app/asyncComponent/media/sw-media-modal-replace'));
    Shopware.Component.register('sw-media-preview-v2', () => import('src/app/asyncComponent/media/sw-media-preview-v2'));
    // @ts-expect-error - the extended component is not a valid vue configuration
    Shopware.Component.extend('sw-media-replace', 'sw-media-upload-v2', import('src/app/asyncComponent/media/sw-media-replace'));
    Shopware.Component.register('sw-media-upload-v2', () => import('src/app/asyncComponent/media/sw-media-upload-v2'));
    Shopware.Component.register('sw-media-url-form', () => import('src/app/asyncComponent/media/sw-media-url-form'));
    Shopware.Component.register('sw-sidebar-media-item', () => import('src/app/asyncComponent/media/sw-sidebar-media-item'));
    Shopware.Component.register('sw-extension-icon', () => import('src/app/asyncComponent/extension/sw-extension-icon'));
    Shopware.Component.register('sw-ai-copilot-badge', () => import('src/app/asyncComponent/feedback/sw-ai-copilot-badge'));
    Shopware.Component.register('sw-ai-copilot-warning', () => import('src/app/asyncComponent/feedback/sw-ai-copilot-warning'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations, max-len */
};
