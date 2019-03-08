import template from './sw-context-button.html.twig';
import './sw-context-button.scss';

/**
 * @public
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-context-button>
 *     <sw-context-menu-item>
 *         Example item
 *     </sw-context-menu-item>
 * </sw-context-button>
 */
export default {
    name: 'sw-context-button',
    template,

    props: {
        showMenuOnStartup: {
            type: Boolean,
            required: false,
            default: false
        },

        menuWidth: {
            type: Number,
            required: false,
            default: 220
        },

        menuOffsetTop: {
            type: Number,
            required: false,
            default: 10
        },

        menuOffsetLeft: {
            type: Number,
            required: false,
            default: 15
        },

        menuHorizontalAlign: {
            type: String,
            required: false,
            default: 'right',
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['right', 'left'].includes(value);
            }
        },

        menuVerticalAlign: {
            type: String,
            required: false,
            default: 'bottom',
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['bottom', 'top'].includes(value);
            }
        },

        icon: {
            type: String,
            required: false,
            default: 'small-more'
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        autoClose: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            showMenu: this.showMenuOnStartup,
            positionTop: 0,
            positionLeft: 0,
            paddingTop: 0,
            menuUuid: 0
        };
    },

    computed: {
        menuStyles() {
            return {
                left: `${this.positionLeft}px`,
                top: `${this.positionTop}px`,
                display: this.showMenu ? 'block' : 'none',
                width: `${this.menuWidth}px`,
                'padding-top': `${this.paddingTop}px`
            };
        },

        contextClass() {
            return {
                'is--disabled': this.disabled,
                'is--active': this.showMenu
            };
        },

        contextButtonClass() {
            return {
                'is--active': this.showMenu
            };
        },

        contextMenuClass() {
            return {
                'is--left-align': this.menuHorizontalAlign === 'left',
                'is--top-align': this.menuVerticalAlign === 'top'
            };
        }
    },

    mounted() {
        this.mountedComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        mountedComponent() {
            if (this.showMenu) {
                return;
            }
            this.removeMenuFromBody();
        },

        beforeDestroyComponent() {
            this.removeMenuFromBody();
        },

        openMenu() {
            if (this.disabled) {
                return;
            }

            const boundingBox = this.$el.getBoundingClientRect();
            const secureOffset = 5;

            this.positionTop = boundingBox.top - secureOffset;

            if (this.menuHorizontalAlign === 'left') {
                this.positionLeft = boundingBox.left - secureOffset;
            } else {
                this.positionLeft = (boundingBox.left + boundingBox.width + this.menuOffsetLeft) - this.menuWidth;
            }
            this.paddingTop = boundingBox.height + secureOffset + this.menuOffsetTop;

            this.addMenuToBody();
            this.showMenu = true;
        },

        closeMenu(event) {
            const el = this.$refs.swContextButton;
            const target = event.target;
            const excludedElements = this.autoClose ?
                !target.classList.contains('is--disabled') :
                !target.closest('.sw-context-menu__content');

            if ((el !== target) && !el.contains(target) && excludedElements) {
                this.showMenu = false;
                this.removeMenuFromBody();
            }
        },

        addMenuToBody() {
            const menuEl = this.$refs.swContextMenu;

            if (menuEl) {
                document.body.appendChild(menuEl.$el);
                document.addEventListener('click', this.closeMenu);
            }
        },

        removeMenuFromBody() {
            const menuEl = this.$refs.swContextMenu;

            if (menuEl) {
                document.removeEventListener('click', this.closeMenu);
                menuEl.$el.remove();
            }
        }
    }
};
