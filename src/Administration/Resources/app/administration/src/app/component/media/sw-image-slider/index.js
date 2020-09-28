import template from './sw-image-slider.html.twig';
import './sw-image-slider.scss';

const { Component, Filter } = Shopware;

/**
 * @description Renders an image slider with possible image descriptions
 * @status ready
 * @example-type static
 * @component-example
 * <sw-image-slider
 *         :images="[
 *             {
 *                 src: 'my/path/to/asset/test.png',
 *                 description: 'This Image is awesome!'
 *             },
 *             'my/path/to/asset/test2.png',
 *             'http://external.path/to/asset/test3.png',
 *             {
 *                 src: 'http://external.path/to/asset/test4.png',
 *             }
 *         ]"
 *         :canvasWidth="600"
 *         :canvasHeight="300"
 *         overflow="visible"
 *         navigationType="all"
 *         enableDescriptions>
 * </sw-image-slider>
 */
Component.register('sw-image-slider', {
    template,

    props: {
        images: {
            type: Array,
            required: true
        },

        canvasWidth: {
            type: Number,
            required: true
        },

        canvasHeight: {
            type: Number,
            required: true
        },

        gap: {
            type: Number,
            required: false,
            default: 20
        },

        navigationType: {
            type: String,
            required: false,
            default: 'arrow',
            validator(value) {
                return ['arrow', 'button', 'all'].includes(value);
            }
        },

        enableDescriptions: {
            type: Boolean,
            required: false,
            default: false
        },

        overflow: {
            type: String,
            required: false,
            default: 'hidden',
            validator(value) {
                return ['hidden', 'visible'].includes(value);
            }
        }
    },

    data() {
        return {
            currentPageNumber: 0
        };
    },

    computed: {
        componentStyles() {
            return {
                width: `${this.canvasWidth}px`
            };
        },

        containerStyles() {
            return {
                ...this.componentStyles,
                overflowX: this.overflow
            };
        },

        scrollableContainerStyles() {
            return {
                width: `${this.images.length * this.canvasWidth + (this.images.length - 1) * this.gap}px`,
                gap: `${this.gap}px`,
                transform: `translateX(-${this.currentPageNumber * (this.canvasWidth + this.gap)}px)`
            };
        },

        arrowStyles() {
            return {
                height: `${this.canvasHeight}px`
            };
        }
    },

    methods: {
        setCurrentPageNumber(pageNumber) {
            this.currentPageNumber = pageNumber;
        },

        isImageObject(image) {
            return typeof image === 'object';
        },

        hasValidDescription(image) {
            return this.enableDescriptions &&
                this.isImageObject(image) &&
                image.hasOwnProperty('description') &&
                image.description.length >= 1;
        },

        getImage(image) {
            const link = this.isImageObject(image) ? image.src : image;

            try {
                URL(link);
            } catch (e) {
                return Filter.getByName('asset')(link);
            }

            return link;
        },

        imageAlt(index) {
            return this.$tc('sw-image-slider.imageAlt', 0, {
                index: index + 1,
                total: this.images.length
            });
        },

        goToPreviousImage() {
            this.currentPageNumber = Math.max(this.currentPageNumber - 1, 0);
        },

        goToNextImage() {
            this.currentPageNumber = Math.min(this.currentPageNumber + 1, this.images.length - 1);
        },

        elementStyles(image, index) {
            if (index === this.currentPageNumber) {
                return {
                    width: `${this.canvasWidth - 2}px`,
                    height: `${this.canvasHeight}px`,
                    ...this.borderStyles(image)
                };
            }

            return {
                width: `${this.canvasWidth - 2}px`,
                height: `${this.canvasHeight}px`,
                ...this.borderStyles(image),
                cursor: 'pointer'
            };
        },

        borderStyles(image) {
            if (!this.hasValidDescription(image)) {
                return {};
            }

            return {
                borderBottomLeftRadius: 0,
                borderBottomRightRadius: 0
            };
        }
    }
});
