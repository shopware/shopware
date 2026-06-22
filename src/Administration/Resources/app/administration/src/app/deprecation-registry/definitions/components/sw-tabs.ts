import { componentMigration, reference, removeProp, removeSlot, renameComponent, renameProp, slotToItemsProp } from '../helpers';

export default componentMigration({
    id: 'component.sw-tabs',
    component: 'sw-tabs',
    replacement: 'mt-tabs',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-tabs component is replaced by mt-tabs. Simple component and prop renames can be automated, but default/content slot migrations require manual review because mt-tabs expects tab metadata through the items prop and content outside the component.',
    handler: 'mt-tabs',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-tabs' }),
    ],
    usage: [
        renameComponent({
            from: 'sw-tabs',
            to: 'mt-tabs',
            fix: 'manual',
            message: 'Migrate tab metadata to the items prop and move tab panel content outside of mt-tabs.',
        }),
        renameProp({
            from: 'is-vertical',
            to: 'vertical',
        }),
        removeProp({
            prop: 'align-right',
        }),
        slotToItemsProp({
            slot: 'default',
            prop: 'items',
            itemComponent: 'sw-tabs-item',
            fix: 'unsafe-auto',
            message: 'Convert the default slot tab declarations into mt-tabs items.',
        }),
        removeSlot({
            slot: 'content',
            fix: 'unsafe-auto',
            message: 'Move content slot markup out of mt-tabs and render it next to the active tab state.',
        }),
    ],
});
