/**
 * @sw-package framework
 */

import importExportProfileLabel from './import-export-profile-label';
import mailTemplatePreviewFooter from './mail-template-preview-footer';
import newsletterRecipientFilter from './newsletter-recipient-filter';
import renamedAdminBlocks from './renamed-admin-blocks';
import miscBlocks from './misc-blocks';
import productBlocks from './product-blocks';
import cmsBlocks from './cms-blocks';
import marketingBlocks from './marketing-blocks';
import importExportBlocks from './import-export-blocks';
import settingsBlocks from './settings-blocks';
import mediaBlocks from './media-blocks';
import orderBlocks from './order-blocks';
import salesChannelBlocks from './sales-channel-blocks';
import type { TemplateBlockMigration } from '../helpers';

export default [
    importExportProfileLabel,
    mailTemplatePreviewFooter,
    newsletterRecipientFilter,
    renamedAdminBlocks,
    miscBlocks,
    productBlocks,
    cmsBlocks,
    marketingBlocks,
    importExportBlocks,
    settingsBlocks,
    mediaBlocks,
    orderBlocks,
    salesChannelBlocks,
] satisfies TemplateBlockMigration[];
