/**
 * @sw-package discovery
 */

import template from './sw-media-tag.html.twig';
import './sw-media-tag.scss';
import { repositoryFactoryService } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService],

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
