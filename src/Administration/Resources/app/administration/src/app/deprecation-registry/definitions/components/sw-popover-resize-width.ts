/**
 * @sw-package framework
 */

import { componentMigration, flagUsage, renameProp, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-popover-resize-width',
    component: 'sw-popover',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    onlyWhenSelected: true,
    description:
        'The resize-width prop of sw-popover is removed. The wrapper accepts match-reference-width on 6.7 and 6.8. sw-popover itself is replaced by mt-floating-ui, whose anchor, v-if and z-index behave differently.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-popover-resize-width-is-replaced-by-match-reference-width'),
    ],
    onEveryUsage: [
        renameProp({
            from: 'resize-width',
            to: 'match-reference-width',
            replacementWins: true,
            message: 'resize-width of sw-popover is removed in 6.8. Use match-reference-width instead.',
        }),
        flagUsage({
            message:
                'sw-popover is deprecated. Move to mt-floating-ui: v-if → :is-opened, popover-class → class; z-index has no replacement.',
        }),
    ],
});
