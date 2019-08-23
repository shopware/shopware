import template from './sw-avatar.html.twig';
import './sw-avatar.scss';

const { Component } = Shopware;

const colors = [
    '#FFD700',
    '#FFC700',
    '#F88962',
    '#F56C46',
    '#FF85C2',
    '#FF68AC',
    '#6AD6F0',
    '#4DC6E9',
    '#A092F0',
    '#8475E9',
    '#57D9A3',
    '#3CCA88'
];

/**
 * @description The component helps adding a custom user image or initials to the administration.
 * @status ready
 * @example-type static
 * @component-example
 * <div style="display: flex; align-items: center;">
 * <sw-avatar color="#dd4800"
 *            size="48px"
 *            firstName="John"
 *            style="margin: 0 10px;"
 *            lastName="Doe"></sw-avatar>
 *
 * <sw-avatar size="48px"
 *            imageUrl="https://randomuser.me/api/portraits/women/68.jpg"></sw-avatar>
 * </div>
 */
Component.register('sw-avatar', {
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
        firstName: {
            type: String,
            required: false,
            default: ''
        },
        lastName: {
            type: String,
            required: false,
            default: ''
        },
        imageUrl: {
            type: String,
            required: false
        },
        placeholder: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            fontSize: 16,
            lineHeight: 16
        };
    },

    watch: {
        size() {
            this.$nextTick(() => {
                this.generateAvatarInitialsSize();
            });
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
            const firstNameLetter = this.firstName ? this.firstName[0] : '';
            const lastNameLetter = this.lastName ? this.lastName[0] : '';

            return firstNameLetter + lastNameLetter;
        },

        avatarInitialsSize() {
            return {
                'font-size': `${this.fontSize}px`,
                'line-height': `${this.lineHeight}px`
            };
        },

        avatarImage() {
            if (!this.imageUrl) {
                return '';
            }

            return {
                'background-image': `url('${this.imageUrl}')`
            };
        },

        avatarColor() {
            if (this.color.length) {
                return {
                    'background-color': this.color
                };
            }

            const firstNameLength = this.firstName ? this.firstName.length : 0;
            const lastNameLength = this.lastName ? this.lastName.length : 0;

            const nameLength = firstNameLength + lastNameLength;
            const color = colors[nameLength % colors.length];

            return {
                'background-color': color
            };
        },

        showPlaceholder() {
            return this.placeholder && (!this.imageUrl || !this.imageUrl.length);
        },

        showInitials() {
            return !this.placeholder && (!this.imageUrl || !this.imageUrl.length);
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
