/**
 * @sw-package framework
 */

import importExportProfileLabel from './import-export-profile-label';
import mailTemplatePreviewFooter from './mail-template-preview-footer';
import newsletterRecipientFilter from './newsletter-recipient-filter';
import renamedAdminBlocks from './renamed-admin-blocks';
import type { TemplateBlockMigration } from '../helpers';

export default [
    importExportProfileLabel,
    mailTemplatePreviewFooter,
    newsletterRecipientFilter,
    renamedAdminBlocks,
] satisfies TemplateBlockMigration[];
