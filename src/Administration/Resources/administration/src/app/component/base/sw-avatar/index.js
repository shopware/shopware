import template from './sw-avatar.html.twig';
import './sw-avatar.scss';

/**
 * @description The component helps adding a custom user image or initials to the administration.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-avatar color="#dd4800" size="48px" :user="{
 *     firstName: 'John',
 *     lastName: 'Doe'
 * }"></sw-avatar>
 */
export default {
    name: 'sw-avatar',
    template,

    props: {
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

        userImage() {
            return '';
        },

        avatarImage() {
            return {
                'background-image': `url(${this.userImage})`
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
};
