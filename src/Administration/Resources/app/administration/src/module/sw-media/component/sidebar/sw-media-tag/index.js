import template from './sw-media-tag.html.twig';
import './sw-media-tag.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        media: {
            type: Object,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },
    methods: {
        handleChange() {
            this.mediaRepository.save(this.media);
        },
    },
};
