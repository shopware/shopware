import template from './sw-image-preview-modal.html.twig';
import './sw-image-preview-modal.scss';

const { Component } = Shopware;
/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @private
 * @status ready
 * @example-type static
 * @component-example
 * <sw-image-preview-modal
 *     v-if="showImagePreviewModal"
 *     :mediaItems="mediaItems"
 *     :activeItem="activeItemIdId"
 *     @modal-close="onCloseModal">
 * </sw-image-preview-modal>
 */
Component.register('sw-image-preview-modal', {
    template,

    props: {
        mediaItems: {
            type: Array,
            required: true,
        },

        activeItemId: {
            type: String,
            required: false,
            default: '',
        },

        zoomSteps: {
            type: Number,
            required: false,
            default: 5,
        },

        itemPerPage: {
            type: Number,
            required: false,
            default: 10,
        },
    },

    data() {
        return {
            activeItemIndex: 0,
            image: null,
            scale: 1,
            isDisabledReset: true,
            isDisabledZoomIn: true,
            isDisabledZoomOut: true,
            imageSliderMounted: false,
        };
    },

    computed: {
        images() {
            return this.mediaItems.map(item => {
                if (item?.media?.url) {
                    return {
                        ...item.media,
                        src: item.media.url,
                    };
                }

                return item;
            });
        },

        maxZoomValue() {
            if (this.image) {
                const { offsetWidth, offsetHeight, naturalWidth, naturalHeight } = this.image;
                const value = Math.max(naturalWidth / offsetWidth, naturalHeight / offsetHeight);
                return Number.isNaN(value) ? 1 : value;
            }

            return 1;
        },
    },

    created() {
        this.createdComponent();
    },

    updated() {
        this.updatedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            if (this.activeItemId) {
                this.activeItemIndex = this.mediaItems.findIndex(item => item.id === this.activeItemId);
            }
        },

        afterComponentsMounted() {
            if (this.imageSliderMounted) {
                return;
            }
            this.imageSliderMounted = true;

            document.querySelector('body').appendChild(this.$el);

            this.$el.addEventListener('wheel', this.onMouseWheel);

            this.getActiveImage().then(() => {
                this.setActionButtonState();
            });
        },

        updatedComponent() {
            this.getActiveImage().then(() => {
                this.setActionButtonState();
            });
        },

        beforeDestroyComponent() {
            if (this.$parent?.$el !== this.$el) {
                // move DOM element back to vDOM parent so that Vue can remove the DOM entry on changes
                this.$parent.$el.appendChild(this.$el);
            } else {
                this.$el.remove();
            }
        },

        destroyedComponent() {
            this.$el.removeEventListener('wheel', this.onMouseWheel);
        },

        buttonClass(isDisabled) {
            return {
                'is--disabled': isDisabled,
            };
        },

        async getActiveImage() {
            this.image = this.$el.querySelector(
                '.sw-image-preview-modal__image-slider .sw-image-slider__element-image.is--active',
            );

            if (!this.image.complete) {
                this.image = await this.loadImage(this.image);
            }
        },

        loadImage(element) {
            return new Promise((resolve, reject) => {
                element.onload = () => resolve(element);
                element.onerror = reject;
            });
        },

        onClickClose() {
            this.$emit('modal-close');
        },

        onClickZoomIn() {
            const zoomAmount = this.maxZoomValue / this.zoomSteps;

            this.scale = (this.scale + zoomAmount > this.maxZoomValue)
                ? this.maxZoomValue : this.scale + zoomAmount;
            this.setTransition();
            this.updateTransform();
        },

        onClickZoomOut() {
            const zoomAmount = this.maxZoomValue / this.zoomSteps;

            this.scale = (this.scale - zoomAmount < 1) ? 1 : this.scale - zoomAmount;
            this.setTransition();
            this.updateTransform();
        },

        onClickReset() {
            this.scale = 1;
            this.updateTransform();
        },

        onImageSliderChange(index) {
            this.activeItemIndex = index;
            this.scale = 1;
        },

        onThumbnailSliderChange(index) {
            this.activeItemIndex = index;
            this.scale = 1;
        },

        setTransition() {
            const transition = 'all 350ms ease 0s';
            this.image.style.transition = transition;
            this.image.style.WebkitTransition = transition;
            this.image.style.msTransition = transition;
        },

        updateTransform() {
            this.setActionButtonState();

            const transform = `scale(${this.scale})`;
            this.image.style.transform = transform;
            this.image.style.WebkitTransform = transform;
            this.image.style.msTransform = transform;
        },

        setActionButtonState() {
            if (this.scale === 1 && this.maxZoomValue === 1) {
                this.isDisabledReset = true;
                this.isDisabledZoomIn = true;
                this.isDisabledZoomOut = true;
            } else if (this.maxZoomValue <= this.scale) {
                this.isDisabledReset = false;
                this.isDisabledZoomIn = true;
                this.isDisabledZoomOut = false;
            } else if (this.scale === 1) {
                this.isDisabledReset = true;
                this.isDisabledZoomIn = false;
                this.isDisabledZoomOut = true;
            } else {
                this.isDisabledReset = false;
                this.isDisabledZoomIn = false;
                this.isDisabledZoomOut = false;
            }
        },

        onMouseWheel(event) {
            const zoomAmount = event.wheelDelta / 960;

            if (this.scale + zoomAmount > this.maxZoomValue) {
                this.scale = this.maxZoomValue;
            } else if (this.scale + zoomAmount < 1) {
                this.scale = 1;
            } else {
                this.scale += zoomAmount;
            }

            this.setTransition();
            this.updateTransform();
        },
    },
});
