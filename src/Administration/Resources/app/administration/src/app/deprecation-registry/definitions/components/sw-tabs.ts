/**
 * @sw-package framework
 */

import {
    childElementsToArrayProp,
    componentMigration,
    fromAttribute,
    fromText,
    removeProp,
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
        'The legacy sw-tabs component is replaced by mt-tabs, which takes the tab metadata through the items prop instead of sw-tabs-item children.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-tabs-automatic-wrapper-switch-deferred'),
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
    ],
});
