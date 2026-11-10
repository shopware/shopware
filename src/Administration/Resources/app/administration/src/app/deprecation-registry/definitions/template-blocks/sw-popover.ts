/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-popover',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description: 'sw-popover was removed together with its blocks, use mt-floating-ui instead.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-sw-popover'),
    ],
    usage: [
        removeTemplateBlock({
            from: 'sw_popover_deprecated',
            message:
                'Use mt-floating-ui: put the reference element into its "trigger" slot and bind "is-opened", ' +
                'replace "resize-width" with "match-reference-width" and pass "z-index" and ' +
                '"popover-config-extension" through "floating-ui-options".',
        }),
    ],
});
