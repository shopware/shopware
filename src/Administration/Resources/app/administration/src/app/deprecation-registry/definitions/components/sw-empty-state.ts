/**
 * @sw-package framework
 */

import {
    componentMigration,
    missingProp,
    onlyAfterConversion,
    reference,
    removeProp,
    removeSlot,
    renameComponent,
    renameProp,
} from '../helpers';

const UNSUPPORTED_PROPS = [
    'absolute',
    'empty-module',
    'auto-height',
    'color',
    'show-description',
];

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
        reference({ type: 'upgrade', target: 'UPGRADE-6.8.md#removal-of-sw-empty-state' }),
    ],
    usage: [
        renameComponent({ from: 'sw-empty-state', to: 'mt-empty-state' }),
        renameProp({
            from: 'subline',
            to: 'description',
        }),
        renameProp({
            from: 'title',
            to: 'headline',
        }),
        removeSlot({
            slot: 'actions',
            fix: 'manual',
            message:
                'The "actions" slot is not supported by mt-empty-state. Use the "button" slot, or the button-text prop with @button-click, or link-text with link-href.',
        }),
        removeSlot({
            slot: 'default',
            fix: 'manual',
            message: 'The default slot is not supported by mt-empty-state. Move its content into the "description" prop.',
        }),
        removeSlot({
            slot: 'icon',
            fix: 'manual',
            message: 'The "icon" slot is not supported by mt-empty-state. Use the "icon" prop instead.',
        }),
        onlyAfterConversion(
            missingProp({
                prop: 'icon',
                value: '$route.meta.$module.icon',
                bind: true,
                fix: 'unsafe-auto',
                message: 'Add an icon because mt-empty-state requires one.',
                reviewHint: 'sw-empty-state took its icon from the route module; check the icon',
            }),
        ),
        ...UNSUPPORTED_PROPS.map((prop) =>
            removeProp({
                prop,
                fix: 'manual',
                message: `The "${prop}" prop is not supported by mt-empty-state. Remove it and check the layout.`,
            }),
        ),
    ],
});
