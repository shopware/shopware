import { aiBadgeToTitleSlotTransform, componentMigration, reference, removeProp, renameComponent } from '../helpers';

export default componentMigration({
    id: 'component.sw-card',
    component: 'sw-card',
    replacement: 'mt-card',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-card component is replaced by mt-card. AI badge and content padding props were removed.',
    handler: 'mt-card',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-card' }),
    ],
    usage: [
        renameComponent({ from: 'sw-card', to: 'mt-card' }),
        removeProp({
            prop: 'ai-badge',
            fix: 'unsafe-auto',
            transform: aiBadgeToTitleSlotTransform,
            message: 'Remove the AI badge prop and render the AI badge directly in the title slot.',
        }),
        removeProp({
            prop: 'content-padding',
        }),
    ],
});
