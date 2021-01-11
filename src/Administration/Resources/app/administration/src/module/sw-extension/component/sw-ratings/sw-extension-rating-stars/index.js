import template from './sw-extension-rating-stars.html.twig';
import './sw-extension-rating-stars.scss';

const defaultSize = 8;
const defaultSizeForEditable = 17;
const paddingStar = 20; // 10% padding left and right
const scaleFactor = (0.0125 * paddingStar) + 1;

export default {
    name: 'sw-extension-rating-stars',
    template,

    model: {
        prop: 'rating',
        event: 'rating-changed'
    },

    props: {
        editable: {
            type: Boolean,
            required: false,
            default: false
        },
        size: {
            type: Number,
            required: false,
            default: defaultSize
        },
        rating: {
            type: Number,
            required: false,
            default: 0
        }
    },

    data() {
        return {
            maxRating: 5,
            ratingValue: null
        };
    },

    watch: {
        rating(value) {
            this.ratingValue = value;
        }
    },

    computed: {
        editableClass() {
            return {
                'is--editable': this.editable
            };
        },

        sizeValue() {
            return this.editable && this.size === defaultSize ? defaultSizeForEditable : this.size;
        },

        starSize() {
            return {
                width: `${this.sizeValue * scaleFactor}px`
            };
        },

        partialStarSize() {
            return `${this.sizeValue}px`;
        },

        partialStarWidth() {
            return {
                width: `${(this.ratingValue % 1) * 100}%`
            };
        }
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
                'is--rated': (this.maxRating + 1) - key <= this.ratingValue
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
        }
    }
};
