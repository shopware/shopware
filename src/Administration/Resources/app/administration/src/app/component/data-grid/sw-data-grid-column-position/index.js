import template from './sw-data-grid-column-position.html.twig';
import './sw-data-grid-column-position.scss';

const { Component, Mixin } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @status ready
 * @description The sw-data-grid-column-position is a sw-data-grid element to be slotted
 *  into a column slot like #column-position.
 * @example-type static
 * @component-example
 *  <template #column-position="{ item }">
 *      <sw-data-grid-column-position
 *          v-model="collection"
 *          :item="item"
 *          field="myPositionProperty"
 *          :disabled="!!term">
 *      </sw-data-grid-column-position>
 *  </template>
 */
Component.register('sw-data-grid-column-position', {
    template,

    mixins: [
        Mixin.getByName('position'),
    ],

    props: {
        value: {
            type: Array,
            required: true,
        },
        item: {
            type: Object,
            required: true,
        },
        field: {
            type: String,
            required: false,
            default: 'position',
        },
        showValue: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        itemMin() {
            return this.value.every((entity) => this.item[this.field] <= entity[this.field]);
        },

        itemMax() {
            return this.value.every((entity) => this.item[this.field] >= entity[this.field]);
        },
    },

    methods: {
        onLowerPositionValue() {
            this.lowerPositionValue(this.value, this.item);
            this.$emit('lower-position-value', this.value);
            this.$emit('position-changed', this.value);
        },

        onRaisePositionValue() {
            this.raisePositionValue(this.value, this.item);
            this.$emit('raise-position-value', this.value);
            this.$emit('position-changed', this.value);
        },
    },
});
