import template from './sw-image-slider.html.twig';
import './sw-image-slider.scss';

const { Filter } = Shopware;

/**
 * @description Renders an image slider with possible image descriptions
 * @status ready
 * @package content
 * @example-type static
 * @component-example
 * <sw-image-slider
 *     :images="[
 *         {
 *             src: 'https://www.shopware.com/media/unknown/29/03/ce/shopware_logo_white_on_blue.svg',
 *             description: 'This Image is awesome!'
 *         },
 *         'https://www.shopware.com/media/image/5e/98/6d/small-differentiator-illustration-01.png',
 *         {
 *             src: 'https://www.shopware.com/media/image/72/44/fd/image-material.jpg',
 *         }
 *     ]"
 *     :canvas-width="600"
 *     :canvas-height="300"
 *     overflow="visible"
 *     navigation-type="all"
 *     enable-descriptions
 * ></sw-image-slider>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        images: {
            type: Array,
            required: true,
        },

        canvasWidth: {
            type: Number,
            required: false,
            default: 0,
            validator(value) {
                return value >= 0;
            },
        },

        canvasHeight: {
            type: Number,
            required: false,
            default: 0,
            validator(value) {
                return value >= 0;
            },
        },

        gap: {
            type: Number,
            required: false,
            default: 20,
            validator(value) {
                return value >= 0;
            },
        },

        elementPadding: {
            type: Number,
            required: false,
            default: 0,
            validator(value) {
                return value >= 0;
            },
        },

        navigationType: {
            type: String,
            required: false,
            default: 'arrow',
            validator(value) {
                return ['arrow', 'button', 'all'].includes(value);
            },
        },

        enableDescriptions: {
            type: Boolean,
            required: false,
            default: false,
        },

        overflow: {
            type: String,
            required: false,
            default: 'hidden',
            validator(value) {
                return ['hidden', 'visible'].includes(value);
            },
        },

        rewind: {
            type: Boolean,
            required: false,
            default: false,
        },

        bordered: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        rounded: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        autoWidth: {
            type: Boolean,
            required: false,
            default: false,
        },

        itemPerPage: {
            type: Number,
            required: false,
            default: 1,
        },

        initialIndex: {
            type: Number,
            required: false,
            default: 0,
        },

        arrowStyle: {
            type: String,
            required: false,
            default: 'inside',
            validator(value) {
                return ['inside', 'outside', 'none'].includes(value);
            },
        },

        buttonStyle: {
            type: String,
            required: false,
            default: 'outside',
            validator(value) {
                return ['inside', 'outside', 'none'].includes(value);
            },
        },

        displayMode: {
            type: String,
            required: false,
            default: 'cover',
            validator(value) {
                return ['contain', 'cover', 'none'].includes(value);
            },
        },
    },

    data() {
        return {
            currentPageNumber: 0,
            currentItemIndex: 0,
        };
    },

    computed: {
        totalPage() {
            return Math.ceil((this.images.length) / this.itemPerPage);
        },

        remainder() {
            return this.images.length % this.itemPerPage;
        },

        buttonList() {
            if (this.itemPerPage === 1) {
                return this.images;
            }

            return this.images.filter((image, index) => {
                return index % this.itemPerPage === 0;
            });
        },

        wrapperStyles() {
            return {
                width: this.canvasWidth ? `${this.canvasWidth}px` : '100%',
            };
        },

        componentStyles() {
            return {
                width: this.autoWidth ? 'auto' : `${100 / this.images.length}%`,
            };
        },

        containerStyles() {
            const offset = this.arrowStyle === 'outside' ? 112 : 0;
            const width = this.canvasWidth ?
                `${this.canvasWidth - offset}px`
                : `calc(100% - ${offset}px)`;

            return {
                width,
                overflowX: this.overflow,
                margin: this.arrowStyle === 'outside' ? '0 56px' : 0,
            };
        },

        scrollableContainerStyles() {
            if (this.itemPerPage === 1
                || this.remainder === 0
                || this.images.length <= this.itemPerPage) {
                return {
                    width: `${this.totalPage * 100}%`,
                    gap: `${this.gap}px`,
                    transform: `translateX(-${(this.currentPageNumber / this.totalPage) * 100}%)`,
                };
            }

            const itemWidth = 100 / this.images.length;
            const translateAmount = (this.currentPageNumber === this.totalPage - 1)
                ? ((this.currentPageNumber - 1) * this.itemPerPage + this.remainder) * itemWidth
                : (this.currentPageNumber * this.itemPerPage) * itemWidth;

            return {
                width: `${(this.totalPage - 1 + this.remainder / this.itemPerPage) * 100}%`,
                gap: `${this.gap}px`,
                transform: `translateX(-${translateAmount}%)`,
            };
        },

        imageStyles() {
            return {
                objectFit: this.displayMode,
            };
        },

        buttonClasses() {
            return { 'is--button-inside': this.buttonStyle === 'inside' };
        },

        showButtons() {
            return this.images.length >= 2
                && this.images.length > this.itemPerPage
                && ['button', 'all'].includes(this.navigationType);
        },

        showArrows() {
            return this.images.length > this.itemPerPage
                && ['arrow', 'all'].includes(this.navigationType);
        },
    },

    watch: {
        initialIndex: {
            immediate: true,
            handler(value) {
                this.onSetCurrentItem(value);
            },
        },
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
                // eslint-disable-next-line no-new
                new URL(link);
            } catch (e) {
                return Filter.getByName('asset')(link);
            }

            return link;
        },

        imageAlt(index) {
            return this.$tc('sw-image-slider.imageAlt', 0, {
                index: index + 1,
                total: this.images.length,
            });
        },

        goToPreviousImage() {
            this.currentPageNumber = (this.rewind && this.currentPageNumber === 0)
                ? this.totalPage - 1
                : Math.max(this.currentPageNumber - 1, 0);

            if (this.itemPerPage === 1) {
                this.currentItemIndex = this.currentPageNumber;
                this.$emit('image-change', this.currentPageNumber);
            }
        },

        goToNextImage() {
            this.currentPageNumber = (this.rewind && this.currentPageNumber === this.totalPage - 1)
                ? 0
                : Math.min(this.currentPageNumber + 1, this.totalPage - 1);

            if (this.itemPerPage === 1) {
                this.currentItemIndex = this.currentPageNumber;
                this.$emit('image-change', this.currentPageNumber);
            }
        },

        elementClasses(index) {
            return [
                { 'is--active': index === this.currentItemIndex && this.itemPerPage > 1 },
                { 'is--bordered': this.bordered },
                { 'is--rounded': this.rounded },
            ];
        },

        elementStyles(image, index) {
            return {
                cursor: index === this.currentItemIndex ? 'default' : 'pointer',
                height: this.canvasHeight ? `${this.canvasHeight}px` : '100%',
                padding: this.elementPadding ? `${this.elementPadding}px` : 0,
                ...this.borderStyles(image),
            };
        },

        imageClasses(index) {
            return {
                'is--active': index === this.currentItemIndex,
                'is--auto-width': this.autoWidth,
            };
        },

        borderStyles(image) {
            if (!this.hasValidDescription(image)) {
                return {};
            }

            return {
                borderBottomLeftRadius: 0,
                borderBottomRightRadius: 0,
            };
        },

        onSetCurrentItem(index) {
            if (index === this.currentItemIndex) {
                return;
            }

            this.currentPageNumber = Math.floor(index / this.itemPerPage);
            this.currentItemIndex = index;
            this.$emit('image-change', index);
        },

        isHiddenItem(index) {
            if (this.itemPerPage === 1) {
                return index !== this.currentItemIndex;
            }

            if (this.currentPageNumber === this.totalPage - 1) {
                return index < this.images.length - this.itemPerPage;
            }

            return this.currentPageNumber * this.itemPerPage > index
                || index >= (this.currentPageNumber + 1) * this.itemPerPage;
        },
    },
};
