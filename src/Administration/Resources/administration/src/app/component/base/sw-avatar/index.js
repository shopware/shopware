import { Component } from 'src/core/shopware';
import template from './sw-avatar.html.twig';
import './sw-avatar.less';

Component.register('sw-avatar', {
    template,

    data() {
        return {
            fontSize: 16,
            lineHeight: 16
        };
    },

    props: {
        image: {
            type: String,
            required: false,
            default: ''
        },
        color: {
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
                return {
                    firstName: '',
                    lastName: ''
                };
            }
        }
    },

    mounted() {
        this.generateAvatarInitialsSize();
    },

    methods: {
        generateAvatarInitialsSize() {
            const avatarSize = this.$refs.swAvatar.offsetHeight;

            this.fontSize = Math.round(avatarSize * 0.4);
            this.lineHeight = Math.round(avatarSize * 0.98);
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

        avatarInitials() {
            const user = this.user;

            if (!user.firstName && !user.lastName) {
                return '';
            }

            const firstNameLetter = user.firstName ? user.firstName.substring(0, 1) : '';
            const lastNameLetter = user.lastName ? user.lastName.substring(0, 1) : '';

            return `${firstNameLetter} ${lastNameLetter}`;
        },

        avatarInitialsSize() {
            return {
                'font-size': `${this.fontSize}px`,
                'line-height': `${this.lineHeight}px`
            };
        },

        avatarImage() {
            return {
                'background-image': `url(${this.image})`
            };
        },

        avatarColor() {
            return {
                'background-color': this.color
            };
        }
    }
});
