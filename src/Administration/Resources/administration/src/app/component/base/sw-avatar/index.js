import { Component } from 'src/core/shopware';
import template from './sw-avatar.html.twig';
import './sw-avatar.less';

Component.register('sw-avatar', {
    template,

    props: {
        image: {
            type: String,
            required: false,
            default: ''
        },
        size: {
            type: String,
            required: false
        },
        user: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
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

        userInitials() {
            const user = this.user;

            if (!user.firstName || !user.lastName) {
                return '';
            }

            const firstNameLetter = user.firstName.substring(0, 1);
            const lastNameLetter = user.lastName.substring(0, 1);

            return `${firstNameLetter} ${lastNameLetter}`;
        },

        avatarImage() {
            return {
                'background-image': `url(${this.image})`
            };
        }
    }
});
