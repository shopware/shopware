import { Component } from 'src/core/shopware';
import './sw-avatar.less';
import template from './sw-avatar.html.twig';

Component.register('sw-avatar', {
    template,

    props: {
        image: {
            type: String,
            required: false,
            default: 'https://www.menkind.co.uk/media/catalog/product/cache/image/1000x/beff4985b56e3afdbeabfc89641a4582/s/t/star-wars-stormtrooper-robot-62272-_5_.jpg'
        },
        size: {
            type: String,
            required: false
        }
    },

    computed: {
        avatarSize() {
            const size = this.size;

            return {
                width: size,
                height: size
            };
        },

        avatarImage() {
            return {
                'background-image': `url(${this.image})`
            };
        }
    }
});
