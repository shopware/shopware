/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-block', () => import('./sw-cms-block'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-block-config', () => import('./sw-cms-block/sw-cms-block-config'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-block-layout-config', () => import('./sw-cms-block/sw-cms-block-layout-config'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-slot', () => import('./sw-cms-slot'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-mapping-field', () => import('./sw-cms-mapping-field'));
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-toolbar', () => import('./sw-cms-toolbar'));
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-list-item', () => import('./sw-cms-list-item'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-stage-add-block', () => import('./sw-cms-stage-add-block'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-page-form', () => import('./sw-cms-page-form'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-page-select', () => import('./sw-cms-page-select'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-product-box-preview', () => import('./sw-cms-product-box-preview'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-layout-modal', () => import('./sw-cms-layout-modal'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-section', () => import('./sw-cms-section'));
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-section-config', () => import('./sw-cms-section/sw-cms-section-config'));
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-section-actions', () => import('./sw-cms-section/sw-cms-section-actions'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-stage-add-section', () => import('./sw-cms-stage-add-section'));
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-stage-section-selection', () => import('./sw-cms-stage-section-selection'));
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-sidebar', () => import('./sw-cms-sidebar'));
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-sidebar-nav-element', () => import('./sw-cms-sidebar/sw-cms-sidebar-nav-element'));
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-create-wizard', () => import('./sw-cms-create-wizard'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-layout-assignment-modal', () => import('./sw-cms-layout-assignment-modal'));
/*
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-missing-element-modal', () => import('./sw-cms-missing-element-modal'));
/*
 * @private
 * @package content
 */
Shopware.Component.extend(
    'sw-cms-product-assignment',
    'sw-many-to-many-assignment-card',
    () => import('./sw-cms-product-assignment'),
);
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-visibility-config', () => import('./sw-cms-visibility-config'));
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-visibility-toggle', () => import('./sw-cms-visibility-toggle'));
