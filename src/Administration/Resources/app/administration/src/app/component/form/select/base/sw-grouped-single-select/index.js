import './sw-grouped-single-select.scss';
import template from './sw-grouped-single-select.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @description The <u>sw-grouped-single-select</u> component can be used to show a single sleect with grouped result items.
 * @example-type code-only
 * @component-example
 * <sw-grouped-single-select
 *     v-model="itemId"
 *     label="Grouped single select"
 *     :options="[
 *          {
 *                "label": "Option 1",
 *                "group": "exampleGroup1",
 *                "value": "option1"
 *          },
 *          {
 *                "label": "Option 2",
 *                "group": "exampleGroup1",
 *                "value": "option2"
 *          },
 *          {
 *                "label": "Option 3",
 *                "group": "exampleGroup2",
 *                "value": "option3"
 *          }]"
 *      groups="[
 *          {"id": "exampleGroup1", "label": "Example group one"},
 *          {"id": "exampleGroup2", "label": "Example group two"},
 *      ]"
 * </sw-grouped-single-select>
 */
Component.extend('sw-grouped-single-select', 'sw-single-select', {
    template,

    inject: ['feature'],

    props: {
        groups: {
            type: Array,
            required: true,
        },
        /** Property of a group that is used to identify them */
        groupIdProperty: {
            type: String,
            required: false,
            default: 'id',
        },
    },

    methods: {
        getGroupClasses(item) {
            const classes = ['sw-grouped-single-select__group-separator'];
            if (item.group === 'misc') {
                classes.push('sw_grouped-single-select_group-misc-separator');
            }

            return classes;
        },

        getGroupLabel(item) {
            const itemGroup = this.groups.find(group => group[this.groupIdProperty] === item.group);

            return itemGroup?.label ?? '';
        },

        shouldShowGroupTitle(item, index) {
            return item.group && item.group !== this.visibleResults[index - 1]?.group;
        },
    },

});
