/**
 * @sw-package framework
 */

import {
    childElementsToArrayProp,
    componentMigration,
    flagSlotForManualMove,
    fromAttribute,
    fromText,
    removeProp,
    renameComponent,
    renameProp,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-tabs',
    component: 'sw-tabs',
    replacement: 'mt-tabs',
    deprecatedIn: '6.7.0',
    removedIn: '6.9.0',
    description:
        'The legacy sw-tabs component is replaced by mt-tabs. Simple component and prop renames can be automated, but default/content slot migrations require manual review because mt-tabs expects tab metadata through the items prop and content outside the component.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-tabs-automatic-wrapper-switch-deferred'),
    ],
    onConvert: [
        renameComponent({
            from: 'sw-tabs',
            to: 'mt-tabs',
            fix: 'manual',
            message: 'Migrate tab metadata to the items prop and move tab panel content outside of mt-tabs.',
        }),
    ],
    onEveryUsage: [
        renameProp({
            from: 'is-vertical',
            to: 'vertical',
        }),
        removeProp({
            prop: 'align-right',
        }),
        childElementsToArrayProp({
            childElement: 'sw-tabs-item',
            arrayProp: 'items',
            eachItem: {
                label: fromText(),
                name: fromAttribute('name', { required: true }),
                hasError: fromAttribute('has-error'),
                disabled: fromAttribute('disabled'),
            },
        }),
        flagSlotForManualMove({
            slot: 'content',
            message: 'Move content slot markup out of mt-tabs and render it next to the active tab state.',
            hint: 'The "content" slot is not used anymore. Please set the content manually outside the component.',
        }),
    ],
});
