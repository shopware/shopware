/**
 * @sw-package framework
 */

import agenticCommerce from './agentic-commerce';
import cmsElementConfigRenames from './cms-element-config-renames';
import emptyStateAnchors from './empty-state-anchors';
import importExportProfileLabel from './import-export-profile-label';
import importExportViews from './import-export-views';
import languageSettings from './language-settings';
import legacySwTabsContent from './legacy-sw-tabs-content';
import legacySwTabsItems from './legacy-sw-tabs-items';
import legacySwTabsMarkup from './legacy-sw-tabs-markup';
import mailTemplatePreviewFooter from './mail-template-preview-footer';
import newsletterRecipientFilter from './newsletter-recipient-filter';
import orderDetail from './order-detail';
import productPackaging from './product-packaging';
import promotionEmptyStateHero from './promotion-empty-state-hero';
import removedAdminBlocks from './removed-admin-blocks';
import renamedAdminBlocks from './renamed-admin-blocks';
import ruleConditionComponents from './rule-condition-components';
import searchSettings from './search-settings';
import snippetSetList from './snippet-set-list';
import storefrontSettings from './storefront-settings';
import swPopover from './sw-popover';
import type { TemplateBlockMigration } from '../helpers';

export default [
    agenticCommerce,
    cmsElementConfigRenames,
    emptyStateAnchors,
    importExportProfileLabel,
    importExportViews,
    languageSettings,
    legacySwTabsContent,
    legacySwTabsItems,
    legacySwTabsMarkup,
    mailTemplatePreviewFooter,
    newsletterRecipientFilter,
    orderDetail,
    productPackaging,
    promotionEmptyStateHero,
    removedAdminBlocks,
    renamedAdminBlocks,
    ruleConditionComponents,
    searchSettings,
    snippetSetList,
    storefrontSettings,
    swPopover,
] satisfies TemplateBlockMigration[];
