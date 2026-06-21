import { componentMigration, reference, removeProp, renameComponent, renameProp } from '../helpers';

export default componentMigration({
    id: 'component.sw-popover',
    component: 'sw-popover',
    replacement: 'mt-floating-ui',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-popover component is replaced by mt-floating-ui from the Meteor component library. The new component has a different visibility model and several legacy layout props were removed.',
    handler: 'mt-floating-ui',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-popover' }),
    ],
    usage: [
        renameComponent({ from: 'sw-popover', to: 'mt-floating-ui', fix: 'unsafe-auto' }),
        removeProp({
            prop: 'z-index',
            fix: 'manual',
        }),
        renameProp({
            from: 'resize-width',
            to: 'match-reference-width',
        }),
        removeProp({
            prop: 'popover-class',
            fix: 'manual',
        }),
        renameProp({
            from: 'open',
            to: 'is-opened',
            fix: 'manual',
        }),
    ],
});
