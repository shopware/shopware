/**
 * @sw-package framework
 *
 * Three more layouts for the report of direction "One field". Each has a page of its own, so they can be
 * compared side by side.
 */
import { defineComponent, h } from 'vue';
import type { Direction } from './directions';
import OneField from './directions/direction-one-field.vue';

type Layout = 'tabs' | 'table' | 'split';

/** Direction "One field" with one of its layouts. The review shell passes the state on through the attributes. */
function oneFieldWith(layout: Layout) {
    return defineComponent({
        inheritAttrs: false,
        setup:
            (_props, { attrs }) =>
            () =>
                h(OneField, { ...attrs, layout }),
    });
}

/** @private */
export const ONE_FIELD_LAYOUTS: readonly Direction[] = [
    {
        id: 'one-field-tabs',
        name: 'One field · Tabs',
        idea: 'One tab per kind of change. Each component stands once per tab, with the fix in view.',
        component: oneFieldWith('tabs'),
    },
    {
        id: 'one-field-table',
        name: 'One field · Table',
        idea: 'One row per component and one column per kind of change.',
        component: oneFieldWith('table'),
    },
    {
        id: 'one-field-split',
        name: 'One field · Split',
        idea: 'A tree of the changed units on the left with its own search. The picked entry says what changed and shows the code, the plugin and the fix in tabs.',
        component: oneFieldWith('split'),
    },
];
