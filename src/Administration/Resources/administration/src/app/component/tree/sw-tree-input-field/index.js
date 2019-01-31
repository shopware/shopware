import { Component } from 'src/core/shopware';
import template from './sw-tree-input-field.html.twig';
import './sw-tree-input-field.scss';

/**
 * @public
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-tree-input-field>
 * </sw-tree-input-field>
 */
Component.register('sw-tree-input-field', {
    template,

    props: {
        currentValue: {
            type: String,
            required: false
        }
    },

    methods: {
        createNewItem(itemName) {
            this.$emit('createNewItem', itemName);
        }
    }
});
