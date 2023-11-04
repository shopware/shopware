import template from './sw-extension-rating-stars.html.twig';
import './sw-extension-rating-stars.scss';

/**
 * @package merchant-services
 * @private
 */
export default {
    template,

    model: {
        prop: 'rating',
        event: 'rating-changed',
    },

    props: {
        editable: {
            type: Boolean,
            required: false,
            default: false,
        },
        size: {
            type: Number,
            required: false,
            default: 8,
        },
        rating: {
            type: Number,
            required: false,
            default: 0,
        },
    },

    data() {
        return {
            maxRating: 5,
            ratingValue: null,
        };
    },

    computed: {
        editableClass() {
            return {
                'sw-extension-rating-stars--is-editable': this.editable,
            };
        },

        sizeValue() {
            // 8 is the default value of the property `this.size`
            return this.editable && this.size === 8 ? this.defaultSizeForEditable : this.size;
        },

        starSize() {
            return {
                width: `${this.sizeValue * this.scaleFactor}px`,
            };
        },

        partialStarSize() {
            return `${this.sizeValue}px`;
        },

        partialStarWidth() {
            return `${(this.ratingValue % 1) * 100}%`;
        },

        defaultSizeForEditable() {
            return 17;
        },

        scaleFactor() {
            return 0.0125 * 20 + 1;
        },
    },

    watch: {
        rating(value) {
            this.ratingValue = value;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.ratingValue = this.rating;
        },

        colorClass(key) {
            return {
                // subtract because rtl direction is used
                'sw-extension-rating-stars__star--is-rated': this.maxRating + 1 - key <= this.ratingValue,
            };
        },

        addRating(rating) {
            if (!this.editable) {
                return;
            }

            // subtract because rtl direction is used
            this.ratingValue = this.maxRating - rating;
            this.$emit('rating-changed', this.ratingValue);
        },

        showPartialStar(key) {
            return this.ratingValue % 1 !== 0
                // subtract because rtl direction is used
                && (this.maxRating - Math.ceil(this.ratingValue)) === key;
        },
    },
};
