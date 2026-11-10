/**
 * @sw-package framework
 */

import { apiUsageMigration, moduleSnippets, upgradeGuide } from '../helpers';

export default apiUsageMigration({
    id: 'api.module-register-snippets',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Module.register() throws for the snippets property. The Administration loads the <language>.json and <language>-<COUNTRY>.json files below Resources/app/administration/src of active plugins on its own.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-snippets-property-of-moduleregister'),
    ],
    usage: [
        moduleSnippets({
            message:
                'Module.register() no longer takes snippets in 6.8. Name the snippet files <language>.json or <language>-<COUNTRY>.json, move them below Resources/app/administration/src and remove the property.',
        }),
    ],
});
