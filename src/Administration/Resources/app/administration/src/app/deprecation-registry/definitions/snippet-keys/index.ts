/**
 * @sw-package framework
 */

import conditionRemovedKeys from './condition-removed-keys';
import importExportProfileTooltip from './import-export-profile-tooltip';
import type { SnippetKeyMigration } from '../helpers';

export default [
    conditionRemovedKeys,
    importExportProfileTooltip,
] satisfies SnippetKeyMigration[];
