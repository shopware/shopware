import { componentMigration, reference, renameComponent } from '../helpers';

export default componentMigration({
    id: 'component.sw-text-editor-toolbar',
    component: 'sw-text-editor-toolbar',
    replacement: 'mt-text-editor',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-text-editor-toolbar component is replaced by mt-text-editor.',
    handler: 'mt-text-editor',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-text-editor' }),
    ],
    usage: [
        renameComponent({ from: 'sw-text-editor-toolbar', to: 'mt-text-editor', fix: 'manual' }),
    ],
});
