import template from './sw-extension-store-slider.html.twig';
import './sw-extension-store-slider.scss';

const maxSlides = 3;
const { Component } = Shopware;

Component.register('sw-extension-store-slider', {
    template,

    props: {
        images: {
            type: Array,
            required: true
        },
        infinite: {
            type: Boolean,
            required: false,
            default: false
        },
        slideCount: {
            type: Number,
            required: false,
            default: 2
        },
        large: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            activeImgIndex: 0,
            lastActiveImgIndex: null,
            isDirectionRight: null
        };
    },

    computed: {
        cardClasses() {
            return {
                'sw-card--large': this.large
            };
        },

        lastActive() {
            return this.activeImgIndex + this.usedSlideCount - 1;
        },

        isDisabledNext() {
            return this.isInfinite ? false : this.lastActive === this.images.length - 1;
        },

        isDisabledPrevious() {
            return this.isInfinite ? false : this.activeImgIndex === 0;
        },

        sliderHasOneImageMoreThanTheSlideCount() {
            return this.images.length === this.usedSlideCount + 1;
        },

        isInfinite() {
            return this.sliderHasOneImageMoreThanTheSlideCount ? false : this.infinite;
        },

        usedSlideCount() {
            if (this.slideCount > maxSlides) {
                return maxSlides;
            }

            return this.slideCount < 1 ? 1 : this.slideCount;
        }
    },

    methods: {
        getActiveStyle(key) {
            if (!this.isActive(key)) {
                return {};
            }

            const move = 100 / this.usedSlideCount;

            // handle start position after component creation
            if (this.isDirectionRight === null) {
                return {
                    left: `${key * move}%`
                };
            }

            return this.moveActiveImage(key, move);
        },

        moveActiveImage(key, move) {
            // guarantee that first active image is always left
            if (key === this.activeImgIndex) {
                return {
                    left: '0%'
                };
            }

            if (!this.$refs.movingImageWrapper) {
                return {};
            }

            const image = this.$refs.movingImageWrapper.querySelector(`[data-key="${key}"]`);
            const position = parseInt(image.style.left, 10);

            // handle images that were not active before
            if (Number.isNaN(position)) {
                return {
                    left: `${100 - move}%`
                };
            }

            return {
                left: this.isDirectionRight ? `${position - move}%` : `${position + move}%`
            };
        },

        next() {
            this.isDirectionRight = true;
            this.changeSlide(+1);
        },

        previous() {
            this.isDirectionRight = false;
            this.changeSlide(-1);
        },

        changeSlide(value) {
            this.lastActiveImgIndex = this.activeImgIndex;

            if (this.isInfinite) {
                if (this.activeImgIndex === 0 && !this.isDirectionRight) {
                    this.activeImgIndex = this.images.length - 1;
                    return;
                }

                if (this.activeImgIndex === this.images.length - 1 && this.isDirectionRight) {
                    this.activeImgIndex = 0;
                    return;
                }
            }

            this.activeImgIndex += value;
        },

        isActive(index) {
            let isActive = false;

            for (let i = 0; i < this.usedSlideCount; i += 1) {
                if (isActive) {
                    return true;
                }

                isActive = this.activeImgIndex + i === index;
            }

            return isActive
                || (this.lastActive > this.images.length - 1
                    && index <= (this.lastActive) - this.images.length);
        },

        isNext(index) {
            const next = this.activeImgIndex + this.usedSlideCount;

            return next === index
                || (next > this.images.length - 1
                    && (next) - this.images.length === index);
        },

        slideClasses(index) {
            if (this.sliderHasOneImageMoreThanTheSlideCount) {
                return {
                    'is--previous': index === 0 && !this.isActive(index),
                    'is--next': this.images.length - 1 === index && !this.isActive(index),
                    'is--active': this.isActive(index)
                };
            }

            return {
                'is--previous': this.activeImgIndex - 1 === index
                    || (this.activeImgIndex === 0 && index === this.images.length - 1),
                'is--active': this.isActive(index),
                'is--next': this.isNext(index)
            };
        }
    }
});
