import template from './sw-text-editor-toolbar.html.twig';
import './sw-text-editor-toolbar.scss';

const { Component, Utils } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-text-editor-toolbar', {
    template,

    props: {
        parentIsActive: {
            type: Boolean,
            required: false,
            default: false,
        },

        isInlineEdit: {
            type: Boolean,
            required: false,
            default: false,
        },

        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        selection: {
            required: false,
            default: null,
        },

        buttonConfig: {
            type: Array,
            required: true,
        },

        isCodeEdit: {
            type: Boolean,
            required: false,
            default: false,
        },

        isTableEdit: {
            type: Boolean,
            required: false,
            default: false,
        },

    },

    data() {
        return {
            position: {},
            range: null,
            arrowPosition: {
                '--left': '49%;',
            },
            activeTags: [],
            currentColor: null,
            currentLink: null,
            leftButtons: [],
            middleButtons: [],
            rightButtons: [],
            tableEdit: false,
            scrollEventHandler: undefined,
        };
    },

    computed: {
        classes() {
            return {
                'is--boxedEdit': !this.isInlineEdit,
            };
        },
    },

    watch: {
        isTableEdit: {
            handler() {
                this.tableEdit = this.isTableEdit;
                this.$nextTick(() => {
                    this.setActiveTags();
                });
            },
        },

        position: {
            handler(newValue) {
                if (newValue.top.replace('px', '') < 0) {
                    this.closeExpandedMenu();
                }
            },
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    unmounted() {
        this.beforeUnmountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.setButtonPositions();
            this.setActiveTags();
        },

        mountedComponent() {
            if (this.isInlineEdit) {
                const body = document.querySelector('body');
                body.appendChild(this.$el);

                this.scrollEventHandler = Utils.throttle(() => {
                    this.setToolbarPosition();
                }, 16);

                document.addEventListener('scroll', this.scrollEventHandler, true);

                this.$device.onResize({
                    listener: this.setToolbarPosition,
                });
            }

            document.addEventListener('mouseup', this.onMouseUp);
            this.setToolbarPosition();

            this.$emit('created-el', this.$el);
            this.$nextTick(() => {
                this.isOverlayingLeft();
            });
        },

        isOverlayingLeft() {
            if (!this.isInlineEdit) {
                return;
            }

            const el = this.$el;
            const leftSidebar = document.querySelector('.sw-admin-menu');
            if (!leftSidebar) {
                return;
            }

            const leftSidebarWidth = leftSidebar.offsetLeft + leftSidebar.offsetWidth;
            if (el.offsetLeft < leftSidebarWidth) {
                this.position.left = `${leftSidebarWidth + 4}px`;
                this.position.right = 'unset';
                const arrowWidth = 8;
                const selectionBoundary = this.range.getBoundingClientRect();

                let left = selectionBoundary.right - (selectionBoundary.width / 2);
                left -= (leftSidebarWidth + arrowWidth);
                this.arrowPosition['--left'] = `${left}px`;
                this.arrowPosition['--right'] = 'unset';
            }
        },

        destroyedComponent() {
            this.closeExpandedMenu();

            document.removeEventListener('scroll', this.scrollEventListener, true);
            document.removeEventListener('mouseup', this.onMouseUp);

            if (this.$el?.parentElement?.contains(this.$el)) {
                this.$el.parentElement.removeChild(this.$el);
            }

            this.$emit('destroyed-el');
        },

        /*
         * @deprecated tag:v6.6.0 - Will be removed
         */
        beforeUnmountedComponent() {},

        onMouseUp(event) {
            const path = [];
            let source = event.target;

            while (source) {
                path.push(source);
                source = source.parentNode;
            }

            if (path.some(element => element.classList?.contains('sw-popover__wrapper'))) {
                return;
            }

            if (!path.includes(this.$el)) {
                if (!this.isInlineEdit && this.selection) {
                    this.setActiveTags();
                } else if (this.activeTags.length > 0) {
                    this.activeTags = [];
                }

                this.closeExpandedMenu();
                return;
            }

            if (path.indexOf(this.$el) > -1 || !this.parentIsActive) {
                return;
            }

            this.setToolbarPosition();
        },

        setToolbarPosition() {
            if (!this.selection) {
                return;
            }

            if (!this.isInlineEdit) {
                this.setSelectionRange();
                return;
            }

            this.setSelectionRange();
            const boundary = this.range.getBoundingClientRect();

            let offsetTop = window.pageYOffset;
            const arrowHeight = 8;

            offsetTop += boundary.top - (this.$el.clientHeight + arrowHeight);

            const middleBoundary = (boundary.left + boundary.width / 2) + 4;
            const halfWidth = this.$el.clientWidth / 2;
            const offsetLeft = middleBoundary - halfWidth;

            this.position = {
                left: `${offsetLeft}px`,
                top: `${offsetTop}px`,
            };
        },

        setSelectionRange() {
            if (this.selection.anchorNode && this.selection.rangeCount > 0) {
                this.range = this.selection.getRangeAt(0);
            }
        },

        setButtonValues(button) {
            if (this.isCodeEdit && button.type !== 'codeSwitch') {
                return button;
            }

            if (button.children) {
                if (typeof button.expanded === 'undefined') {
                    this.$set(button, 'expanded', false);
                }

                button.children.forEach((child) => {
                    this.$set(child, 'active', !!this.activeTags.includes(child.tag));
                });
            }

            if (button.type === 'foreColor' && this.currentColor) {
                button.value = this.currentColor;
                this.currentColor = null;
            }

            if (button.type === 'link') {
                button.value = this.currentLink?.url ?? '';
                button.newTab = this.currentLink?.newTab ?? false;
                button.displayAsButton = this.currentLink?.displayAsButton ?? false;
                button.buttonVariant = this.currentLink?.buttonVariant ?? 'primary';
            }

            this.$set(button, 'active', !!this.activeTags.includes(button.tag));

            return button;
        },

        isDisabled(button) {
            if (!this.isCodeEdit) {
                return false;
            }

            return button.type !== 'codeSwitch';
        },

        handleToolbarClick(event) {
            if (!event.target.classList.contains('sw-text-editor-toolbar')) {
                return;
            }

            this.keepSelection();
        },

        onButtonClick(button, parent = null) {
            if (button.type === 'link') {
                this.handleTextStyleChangeLink(button);
                return;
            }

            if (button.type === 'linkRemove') {
                this.$emit('removeLink');
            }

            if (button.type === 'foreColor') {
                this.keepSelection(true);
            }

            if (!button.children) {
                this.closeExpandedMenu();
            }

            if (parent) {
                parent.children.forEach((child) => {
                    child.active = false;
                });
            }

            this.keepSelection();

            if (button.handler) {
                button.handler(button, parent);
                button.expanded = false;

                return;
            }

            this.$emit('text-style-change', button.type, button.value);

            if (this.isInlineEdit) {
                this.setToolbarPosition();
            }

            this.$nextTick(() => {
                this.setActiveTags();
                button.active = !!this.activeTags.includes(button.tag);
            });
        },

        closeExpandedMenu() {
            this.buttonConfig.forEach((item) => {
                if (item.expanded) {
                    if (item.type !== 'codeSwitch') {
                        item.expanded = false;
                    }
                }
            });
        },

        setActiveTags() {
            this.currentColor = null;
            this.currentLink = null;

            if (!this.selection || !this.selection.anchorNode) {
                return;
            }

            let parentNode = this.selection.anchorNode.parentNode;
            this.activeTags = [];

            while (parentNode.tagName !== 'DIV') {
                if (parentNode.tagName === 'FONT') {
                    this.currentColor = parentNode.color;
                }

                if (parentNode.tagName === 'A') {
                    const buttonType = parentNode.classList.contains('btn-secondary') ? 'secondary' : 'primary';
                    const buttonSizeSuffix = parentNode.classList.contains('btn-sm') ? '-sm' : '';

                    this.currentLink = {
                        url: parentNode.getAttribute('href'),
                        newTab: parentNode.target === '_blank',
                        displayAsButton: parentNode.classList.contains('btn'),
                        buttonVariant: `${buttonType}${buttonSizeSuffix}`,
                    };
                }

                if (parentNode.tagName === 'TABLE') {
                    if (!this.tableEdit) {
                        this.tableEdit = true;
                        this.$emit('table-edit', this.tableEdit);
                    }
                }

                this.activeTags.push(parentNode.tagName.toLowerCase());

                parentNode = parentNode.parentNode;
            }

            if (this.tableEdit && !this.activeTags.includes('table')) {
                this.tableEdit = false;
                this.$emit('table-edit', this.tableEdit);
            }
        },

        setButtonPositions() {
            this.buttonConfig.forEach((item) => {
                if (!item.position || item.position === 'left') {
                    this.leftButtons.push(item);
                } else if (item.position === 'middle') {
                    this.middleButtons.push(item);
                } else if (item.position === 'right') {
                    this.rightButtons.push(item);
                }
            });
        },

        handleTextStyleChangeLink(button) {
            let target = '_self';
            if (button.newTab) {
                target = '_blank';
            }

            this.keepSelection(true);

            if (button.value) {
                if (!this.selection || this.selection.rangeCount < 1) {
                    button.expanded = false;
                    return;
                }

                this.$emit(
                    'on-set-link',
                    button.value,
                    target,
                    button.displayAsButton,
                    button.buttonVariant,
                );
                this.range = document.getSelection().getRangeAt(0);
                this.range.setStart(this.range.startContainer, 0);
                button.expanded = false;
            }
        },

        keepSelection(keepRange) {
            if (!this.selection) {
                return;
            }

            if (!keepRange) {
                this.setSelectionRange();
            }

            this.selection.removeAllRanges();
            this.selection.addRange(this.range);
        },

        onToggleMenu(event, button) {
            this.keepSelection();

            this.buttonConfig.forEach((item) => {
                if (item === button || item.type === 'codeSwitch') {
                    return;
                }
                if (item.expanded) {
                    item.expanded = false;
                }
            });

            button.expanded = !button.expanded;
        },
    },
});
