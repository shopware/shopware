import template from './sw-cms-el-config-vimeo-video.html.twig';
import './sw-cms-el-config-vimeo-video.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-vimeo-video', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    created() {
        this.createdComponent();
    },

    computed: {
        videoID: {
            get() {
                return this.element.config.videoID.value;
            },

            set(link) {
                this.element.config.videoID.value = this.shortenLink(link);
            }
        }
    },

    methods: {
        createdComponent() {
            this.initElementConfig('vimeo-video');
        },

        shortenLink(link) {
            const videoLink = link;
            const videoIDPrefix = /https:\/\/vimeo\.com\//;
            const videoIDPostfix = /#/;
            let shortenLink = videoLink.replace(videoIDPrefix, '');

            if (videoIDPostfix.test(shortenLink)) {
                const positionOfPostfix = videoIDPostfix.exec(shortenLink).index;
                shortenLink = shortenLink.substring(0, positionOfPostfix);
            }

            return shortenLink;
        }
    }
});
