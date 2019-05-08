import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-cms-el-image-slider.html.twig';
import './sw-cms-el-image-slider.scss';

Component.register('sw-cms-el-image-slider', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    data() {
        return {
            columnCount: 7,
            columnWidth: 90,
            sliderPos: 0,
            imgSrc: '/administration/static/img/cms/preview_mountain_large.jpg'
        };
    },

    computed: {
        uploadStore() {
            return State.getStore('upload');
        },

        mediaStore() {
            return State.getStore('media');
        },

        gridAutoRows() {
            return `grid-auto-rows: ${this.columnWidth}`;
        },

        uploadTag() {
            return `cms-element-media-config-${this.element.id}`;
        },

        sliderItems() {
            if (this.element.data && this.element.data.sliderItems && this.element.data.sliderItems.length > 0) {
                return this.element.data.sliderItems;
            }

            return [];
        },

        displayModeClass() {
            if (this.element.config.displayMode.value === 'standard') {
                return null;
            }

            return `is--${this.element.config.displayMode.value}`;
        },

        styles() {
            if (this.element.config.displayMode.value === 'cover' &&
                this.element.config.minHeight.value !== 0) {
                return {
                    'min-height': this.element.config.minHeight.value
                };
            }

            return {
                'min-height': '340px'
            };
        },

        outsideNavArrows() {
            if (this.element.data && this.element.data.navigation) {
                if (this.element.data.navigation.arrows === 'outside') {
                    return 'has--outside-arrows';
                }
            } else if (this.element.config.navigation.value.arrows === 'outside') {
                return 'has--outside-arrows';
            }

            return null;
        }
    },

    watch: {
        'element.data.sliderItems': {
            handler() {
                if (this.element.data && this.element.data.sliderItems && this.element.data.sliderItems.length > 0) {
                    this.imgSrc = this.element.data.sliderItems[0].media.url;
                } else {
                    this.imgSrc = '/administration/static/img/cms/preview_mountain_large.jpg';
                }
            },
            deep: true
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('image-slider');

            if (this.element.data && this.element.data.sliderItems && this.element.data.sliderItems.length > 0) {
                this.imgSrc = this.element.data.sliderItems[0].media.url;
            }
        },

        setSliderItem(url) {
            this.imgSrc = url;
        },

        activeButtonClass(url) {
            return {
                'is--active': this.imgSrc === url
            };
        },

        setSliderArrowItem(direction = 1) {
            this.sliderPos += direction;

            if (this.sliderPos < 0) {
                this.sliderPos = this.sliderItems.length - 1;
            }

            if (this.sliderPos > this.sliderItems.length - 1) {
                this.sliderPos = 0;
            }

            this.imgSrc = this.sliderItems[this.sliderPos].media.url;
        }
    }
});
