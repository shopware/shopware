/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-block', () => import('./sw-cms-block'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-block-config', () => import('./sw-cms-block/sw-cms-block-config'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-block-layout-config', () => import('./sw-cms-block/sw-cms-block-layout-config'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-slot', () => import('./sw-cms-slot'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-mapping-field', () => import('./sw-cms-mapping-field'));
/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-toolbar', () => import('./sw-cms-toolbar'));
/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-list-item', () => import('./sw-cms-list-item'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-stage-add-block', () => import('./sw-cms-stage-add-block'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-page-form', () => import('./sw-cms-page-form'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-page-select', () => import('./sw-cms-page-select'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-product-box-preview', () => import('./sw-cms-product-box-preview'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-layout-modal', () => import('./sw-cms-layout-modal'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-section', () => import('./sw-cms-section'));
/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-section-config', () => import('./sw-cms-section/sw-cms-section-config'));
/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-section-actions', () => import('./sw-cms-section/sw-cms-section-actions'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-stage-add-section', () => import('./sw-cms-stage-add-section'));
/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-stage-section-selection', () => import('./sw-cms-stage-section-selection'));
/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-sidebar', () => import('./sw-cms-sidebar'));
/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-sidebar-nav-element', () => import('./sw-cms-sidebar/sw-cms-sidebar-nav-element'));
/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-create-wizard', () => import('./sw-cms-create-wizard'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-layout-assignment-modal', () => import('./sw-cms-layout-assignment-modal'));
/*
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-missing-element-modal', () => import('./sw-cms-missing-element-modal'));
/*
 * @private
 * @package buyers-experience
 */
// eslint-disable-next-line max-len
Shopware.Component.extend(
    'sw-cms-product-assignment',
    'sw-many-to-many-assignment-card',
    () => import('./sw-cms-product-assignment'),
);
/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-visibility-config', () => import('./sw-cms-visibility-config'));
/*
 * @package buyers-experience
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-cms-visibility-toggle', () => import('./sw-cms-visibility-toggle'));
