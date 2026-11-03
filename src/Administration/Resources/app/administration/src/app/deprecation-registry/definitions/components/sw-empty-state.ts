/**
 * @sw-package framework
 */

import { codemodRule, componentMigration, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-empty-state',
    component: 'sw-empty-state',
    replacement: 'mt-empty-state',
    deprecatedIn: '6.8.0',
    removedIn: '6.9.0',
    description:
        'The sw-empty-state component is deprecated. Use mt-empty-state and migrate the title prop to headline and the subline prop to description. ' +
        'mt-empty-state requires an icon, which sw-empty-state took from the route module, and has no "actions", "icon" or default slot; ' +
        'use its "button" slot or the button-text, link-text and link-href props for actions.',
    references: [
        upgradeGuide('UPGRADE-6.9.md#sw-empty-state-removed'),
    ],
    usage: [
        codemodRule({
            rule: 'sw-deprecation-rules/no-sw-empty-state-usage',
            from: 'sw-empty-state',
            to: 'mt-empty-state',
            message: 'Usages the rule cannot convert keep sw-empty-state and need a manual migration.',
        }),
    ],
});
