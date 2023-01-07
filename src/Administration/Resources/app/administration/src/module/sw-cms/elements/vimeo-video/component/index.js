import template from './sw-cms-el-vimeo-video.html.twig';
import './sw-cms-el-vimeo-video.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @package content
 */
export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        videoID() {
            return this.element.config.videoID.value;
        },

        byLine() {
            if (this.element.config.byLine.value) {
                return '';
            }

            return `byline=${this.element.config.byLine.value}&`;
        },

        color() {
            if (!this.element.config.color.value) {
                return '';
            }

            return `color=${this.element.config.color.value}&`.replace('#', '');
        },

        doNotTrack() {
            if (!this.element.config.doNotTrack.value) {
                return '';
            }

            return `dnt=${this.element.config.doNotTrack.value}&`;
        },

        loop() {
            if (!this.element.config.loop.value) {
                return '';
            }

            return `loop=${this.element.config.loop.value}&`;
        },

        mute() {
            if (!this.element.config.mute.value) {
                return '';
            }

            return `mute=${this.element.config.mute.value}&`;
        },

        title() {
            if (this.element.config.title.value) {
                return '';
            }

            return `title=${this.element.config.title.value}&`;
        },

        portrait() {
            if (this.element.config.portrait.value) {
                return '';
            }

            return `portrait=${this.element.config.portrait.value}`;
        },

        controls() {
            if (this.element.config.controls.value) {
                return '';
            }

            return `controls=${this.element.config.value}`;
        },

        videoUrl() {
            return `https://player.vimeo.com/video/
            ${this.videoID}?\
            ${this.byLine}\
            ${this.color}\
            ${this.doNotTrack}\
            ${this.loop}\
            ${this.controls}\
            ${this.title}\
            ${this.portrait}`.replace(/ /g, '');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('vimeo-video');
            this.initElementData('vimeo-video');
        },
    },
};
