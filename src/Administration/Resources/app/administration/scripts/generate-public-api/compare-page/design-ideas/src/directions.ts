/**
 * @sw-package framework
 *
 * The directions in the order the overview lists them.
 */
import type { Component } from 'vue';
import ToDo from './directions/direction-to-do.vue';
import Components from './directions/direction-components.vue';
import Listing from './directions/direction-listing.vue';
import OneField from './directions/direction-one-field.vue';

/** @private */
export interface Direction {
    id: string;
    name: string;
    idea: string;
    component: Component;
}

/** @private */
export const DIRECTIONS: readonly Direction[] = [
    {
        id: 'to-do',
        name: 'To-do',
        idea: 'One reading column that turns the breaks into a list of fixes: one item per distinct fix, with every place it applies to.',
        component: ToDo,
    },
    {
        id: 'components',
        name: 'Components',
        idea: 'An index of the changed components, services and stores beside the findings of the one you pick, so you can go straight to the ones your extension touches.',
        component: Components,
    },
    {
        id: 'listing',
        name: 'Listing',
        idea: 'The findings as an Administration list: one row per finding with search, a group filter, sorting and pages, like every other list in Shopware.',
        component: Listing,
    },
    {
        id: 'one-field',
        name: 'One field',
        idea: 'One field takes the comparison, one field narrows the report, and every finding is a single line that opens when you need its fix.',
        component: OneField,
    },
];
