import template from './sw-text-editor-toolbar-button.html.twig';
import './sw-text-editor-toolbar-button.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-text-editor-toolbar-button', {
    template,

    props: {
        buttonConfig: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        isInlineEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            flyoutClasses: [],
            flyoutLinkLeftOffset: 0,
            flyoutLinkMenu: {},
            arrowPosition: 10,
        };
    },

    computed: {
        classes() {
            return {
                'is--active': !!this.buttonConfig.active || this.buttonConfig.expanded,
                'is--disabled': !!this.disabled,
            };
        },

        tooltipAppearance() {
            return this.isInlineEdit ? 'light' : 'dark';
        },

        dynamicPositionStyle() {
            return `left: ${this.flyoutLinkLeftOffset}px; --arrow-position: ${this.arrowPosition}px`;
        },
    },

    updated() {
        this.flyoutLinkMenu = this.$refs?.flyoutLinkMenu;
        this.getLinkMenuPosition();
        window.addEventListener('resize', this.getLinkMenuPosition);
    },

    beforeDestroy() {
        window.removeEventListener('resize', this.getLinkMenuPosition);
    },

    methods: {
        buttonHandler(event, button) {
            if (this.disabled) {
                return null;
            }

            return button.children || button.type === 'link' || button.type === 'table' || button.type === 'foreColor' ?
                this.onToggleMenu(event, button) :
                this.handleButtonClick(button);
        },

        childActive(child) {
            return {
                'is--active': !!child.active,
            };
        },

        handleButtonClick(button, parent = null) {
            if (this.disabled) {
                return;
            }

            this.$emit('button-click', button, parent);
            this.$emit('menu-toggle', null, button);
        },

        onToggleMenu(event, button) {
            if (!['link', 'table', 'foreColor'].includes(button.type) && !button.children) {
                return;
            }

            if (button.type === 'foreColor' && event.target.closest('.sw-colorpicker__colorpicker')) {
                return;
            }

            if (button.type === 'link' && event.target.closest('.sw-text-editor-toolbar-button__link-menu')) {
                return;
            }

            if (button.type === 'table' && event.target.closest('.sw-text-editor-toolbar-button__table-menu')) {
                return;
            }

            if (event.target.closest('.sw-text-editor-toolbar-button__children')) {
                return;
            }

            this.$emit('menu-toggle', event, button);
        },

        getDropdownClasses(buttonConfig) {
            const position = buttonConfig.dropdownPosition || 'right';
            const positionClass = `is--${position}`;

            return [positionClass];
        },

        onChildMounted() {
            const flyoutMenu = this.$refs?.flyoutMenu;

            if (!flyoutMenu || this.flyoutClasses.includes('is--left', 'is--right')) {
                return;
            }

            const flyoutMenuRightBound = flyoutMenu.getBoundingClientRect().right;
            const windowRightBound = this.$root.$el.getBoundingClientRect().right;

            const isOutOfRightBound = flyoutMenuRightBound - windowRightBound > 0;
            this.flyoutClasses = isOutOfRightBound ? ['is--left'] : ['is--right'];
        },

        getTooltipConfig(buttonConfig, child) {
            return {
                disabled: !child.title,
                appearance: this.tooltipAppearance,
                width: 'auto',
                message: child.title,
                showDelay: buttonConfig.tooltipShowDelay || 100,
                hideDelay: buttonConfig.tooltipHideDelay || 100,
            };
        },

        getLinkMenuPosition() {
            const linkIcon = document.querySelector('.sw-text-editor-toolbar-button__type-link');
            const linkIconWidth = linkIcon.clientWidth;
            const linkIconRightBound = linkIcon.getBoundingClientRect().right;

            const flyoutLinkMenuWidth = this.flyoutLinkMenu.clientWidth;

            const linkflyoutMenuRightBound = linkIconRightBound - linkIconWidth + flyoutLinkMenuWidth;
            const windowRightBound = this.$root.$el.getBoundingClientRect().right;

            const isOutOfRightBound = windowRightBound - linkflyoutMenuRightBound;
            if (isOutOfRightBound < 0) {
                this.flyoutLinkLeftOffset = isOutOfRightBound - 50;
                this.arrowPosition = Math.abs(this.flyoutLinkLeftOffset) + 10;
            } else {
                this.flyoutLinkLeftOffset = 0;
                this.arrowPosition = 10;
            }
        },
    },
});
