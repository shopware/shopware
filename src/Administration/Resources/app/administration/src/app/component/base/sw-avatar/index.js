import template from './sw-avatar.html.twig';
import './sw-avatar.scss';

const { Component } = Shopware;
const { cloneDeep } = Shopware.Utils.object;

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
    '#3CCA88',
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
 *
 * <sw-avatar size="48px"
 *            imageUrl="https://randomuser.me/api/portraits/men/68.jpg"
 *            :sourceContext="user"></sw-avatar>
 * </div>
 */
Component.register('sw-avatar', {
    template,

    props: {
        color: {
            type: String,
            required: false,
            default: '',
        },
        size: {
            type: String,
            required: false,
            default: null,
        },
        firstName: {
            type: String,
            required: false,
            default: '',
        },
        lastName: {
            type: String,
            required: false,
            default: '',
        },
        imageUrl: {
            type: String,
            required: false,
            default: null,
        },
        placeholder: {
            type: Boolean,
            required: false,
            default: false,
        },
        sourceContext: {
            type: Object,
            required: false,
            default: null,
        },
        variant: {
            type: String,
            required: false,
            default: 'circle',
            validator: (value) => {
                return ['circle', 'square'].includes(value);
            },
        },
    },

    data() {
        return {
            fontSize: 16,
            lineHeight: 16,
        };
    },

    computed: {
        avatarSize() {
            const size = this.size;

            return {
                width: size,
                height: size,
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
                'line-height': `${this.lineHeight}px`,
            };
        },

        avatarImage() {
            if (this.imageUrl) {
                return { 'background-image': `url('${this.imageUrl}')` };
            }

            if (!this.sourceContext || !this.sourceContext.avatarMedia || !this.sourceContext.avatarMedia.url) {
                return null;
            }

            const avatarMedia = cloneDeep(this.sourceContext.avatarMedia);
            const thumbnailImage = avatarMedia.thumbnails.sort((a, b) => a.width - b.width)[0];
            const previewImageUrl = thumbnailImage ? thumbnailImage.url : avatarMedia.url;

            return { 'background-image': `url('${previewImageUrl}')` };
        },

        avatarColor() {
            if (this.color.length) {
                return {
                    'background-color': this.color,
                };
            }

            const firstNameLength = this.firstName ? this.firstName.length : 0;
            const lastNameLength = this.lastName ? this.lastName.length : 0;

            const nameLength = firstNameLength + lastNameLength;
            const color = colors[nameLength % colors.length];

            return {
                'background-color': color,
            };
        },

        hasAvatarImage() {
            return !!this.avatarImage && !!this.avatarImage['background-image'];
        },

        showPlaceholder() {
            return this.placeholder && !this.hasAvatarImage;
        },

        showInitials() {
            return !this.placeholder && !this.hasAvatarImage;
        },
    },

    watch: {
        size() {
            this.$nextTick(() => {
                this.generateAvatarInitialsSize();
            });
        },
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
        },
    },
});
