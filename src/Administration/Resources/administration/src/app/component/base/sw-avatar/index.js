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

    data() {
        return {
            fontSize: 16,
            lineHeight: 16
        };
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

            const firstNameLetter = user.firstName ? user.firstName[0] : '';
            const lastNameLetter = user.lastName ? user.lastName[0] : '';

            return firstNameLetter + lastNameLetter;
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
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.generateAvatarInitialsSize();
        },

        generateAvatarInitialsSize() {
            const avatarSize = this.$refs.swAvatar.offsetHeight;

            this.fontSize = Math.round(avatarSize * 0.4);
            this.lineHeight = Math.round(avatarSize * 0.98);
        }
    }
});
