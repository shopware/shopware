/**
 * @sw-package framework
 */

import { removeSnippetKey, snippetKeyMigration, upgradeGuide } from '../helpers';

export default snippetKeyMigration({
    id: 'snippet.import-export-profile-tooltip-language',
    deprecatedIn: '6.7.16.0',
    removedIn: '6.8.0',
    description:
        'Import/export profiles can be created in any content language, so the tooltip that asked for the system language is removed without replacement.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#importexport-profile-language-tooltip-removed'),
    ],
    usage: [
        removeSnippetKey({
            from: 'sw-import-export.profile.addNewProfileTooltipLanguage',
            message: 'Remove the tooltip that shows this snippet.',
        }),
    ],
});
