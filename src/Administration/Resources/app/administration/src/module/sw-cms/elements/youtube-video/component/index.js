import template from './sw-cms-el-youtube-video.html.twig';
import './sw-cms-el-youtube-video.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-youtube-video', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        videoID() {
            return this.element.config.videoID.value;
        },

        relatedVideos() {
            return 'rel=0&';
        },

        loop() {
            if (!this.element.config.loop.value) {
                return '';
            }

            return `loop=1&playlist=${this.videoID}&`;
        },

        showControls() {
            if (this.element.config.showControls.value) {
                return '';
            }

            return 'controls=0&';
        },

        start() {
            if (this.element.config.start.value === 0) {
                return '';
            }

            return `start=${this.element.config.start.value}&`;
        },

        end() {
            if (!this.element.config.end.value) {
                return '';
            }

            return `end=${this.element.config.end.value}&`;
        },

        disableKeyboard() {
            return 'disablekb=1';
        },

        videoUrl() {
            const url = `https://www.youtube-nocookie.com/embed/\
            ${this.videoID}?\
            ${this.relatedVideos}\
            ${this.loop}\
            ${this.showControls}\
            ${this.start}\
            ${this.end}\
            ${this.disableKeyboard}`.replace(/ /g, '');

            return url;
        },

        displayModeClass() {
            if (this.element.config.displayMode.value === 'standard') {
                return '';
            }

            return `is--${this.element.config.displayMode.value}`;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('youtube-video');
            this.initElementData('youtube-video');
        },
    },
});
