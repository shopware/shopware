/**
 * @sw-package framework
 */

import {
    componentMigration,
    missingProp,
    onlyAfterConversion,
    propFromSources,
    removeProp,
    renameComponent,
    renameProp,
    renameSlot,
    upgradeGuide,
} from '../helpers';

function withoutReplacement(prop: string): string {
    return `mt-empty-state has no "${prop}" prop: check the look without it.`;
}

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
    // mt-empty-state has no such props or slots, so only elements converted from sw-empty-state are migrated
    usage: [
        renameComponent({ from: 'sw-empty-state', to: 'mt-empty-state' }),
        onlyAfterConversion(renameProp({ from: 'title', to: 'headline' })),
        onlyAfterConversion(
            propFromSources({
                prop: 'description',
                sources: [
                    { slot: 'default' },
                    { prop: 'subline' },
                ],
                fallback: '$t($route.meta.$module.description)',
                condition: { prop: 'show-description', otherwise: '' },
                plainText: true,
            }),
        ),
        onlyAfterConversion(
            propFromSources({
                prop: 'icon',
                sources: [
                    { slot: 'icon', element: ['mt-icon', 'sw-icon'], attribute: 'name' },
                    { prop: 'icon' },
                ],
                fallback: '$route.meta.$module.icon',
            }),
        ),
        onlyAfterConversion(renameSlot({ from: 'actions', to: 'button' })),
        onlyAfterConversion(
            missingProp({
                prop: 'centered',
                message: 'sw-empty-state was centered, mt-empty-state is left-aligned by default.',
            }),
        ),
        onlyAfterConversion(
            removeProp({
                prop: 'absolute',
                trueByDefault: true,
                reviewWhenTrue:
                    'sw-empty-state covered its container, mt-empty-state takes part in the layout: check the spacing.',
            }),
        ),
        onlyAfterConversion(
            removeProp({ prop: 'empty-module', fix: 'unsafe-auto', message: withoutReplacement('empty-module') }),
        ),
        onlyAfterConversion(
            removeProp({ prop: 'auto-height', fix: 'unsafe-auto', message: withoutReplacement('auto-height') }),
        ),
        onlyAfterConversion(removeProp({ prop: 'color', fix: 'unsafe-auto', message: withoutReplacement('color') })),
    ],
});
