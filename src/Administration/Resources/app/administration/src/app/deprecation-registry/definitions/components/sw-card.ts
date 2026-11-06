/**
 * @sw-package framework
 */

import {
    aiBadgeTitleSlot,
    componentMigration,
    removeProp,
    renameComponent,
    renameSlot,
    replaceProp,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-card',
    component: 'sw-card',
    replacement: 'mt-card',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-card component is replaced by mt-card. AI badge and content padding props were removed.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-card'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-card', to: 'mt-card' }),
    ],
    onEveryUsage: [
        replaceProp({
            prop: 'ai-badge',
            with: aiBadgeTitleSlot,
            fix: 'unsafe-auto',
            message: 'Remove the AI badge prop and render the AI badge directly in the title slot.',
        }),
        removeProp({
            prop: 'content-padding',
        }),
        removeProp({
            prop: 'hero',
            fix: 'manual',
            message:
                'mt-card has no "hero" prop, so the card loses its borderless, centered hero look. Remove the prop and restyle the card manually if needed.',
        }),
        renameSlot({ from: 'header-right', to: 'headerRight' }),
    ],
});
