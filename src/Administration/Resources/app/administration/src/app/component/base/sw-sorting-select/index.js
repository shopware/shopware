/**
 * @package admin
 */

import template from './sw-sorting-select.html.twig';
import './sw-sorting-select.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-sorting-select', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        sortBy: {
            type: String,
            default: 'createdAt',
            required: false,
        },

        sortDirection: {
            type: String,
            default: 'DESC',
            required: false,
        },

        additionalSortOptions: {
            type: Array,
            default: () => [],
            required: false,
        },
    },

    computed: {
        sortOptions() {
            return [
                { value: 'name:ASC', name: this.$tc('sw-cms.sorting.labelSortByNameAsc') },
                { value: 'name:DESC', name: this.$tc('sw-cms.sorting.labelSortByNameDesc') },
                { value: 'createdAt:DESC', name: this.$tc('sw-cms.sorting.labelSortByCreatedDsc') },
                { value: 'createdAt:ASC', name: this.$tc('sw-cms.sorting.labelSortByCreatedAsc') },
                { value: 'updatedAt:DESC', name: this.$tc('sw-cms.sorting.labelSortByUpdatedDsc') },
                { value: 'updatedAt:ASC', name: this.$tc('sw-cms.sorting.labelSortByUpdatedAsc') },
                ...this.additionalSortOptions,
            ];
        },

        sortingConditionConcatenation() {
            return `${this.sortBy}:${this.sortDirection}`;
        },
    },


    methods: {
        onSortingChanged(value) {
            const [sortBy, sortDirection] = value.split(':');
            this.$emit('sorting-changed', { sortBy, sortDirection });
        },
    },
});
