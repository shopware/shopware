import './sw-meteor-page-context.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-meteor-page-context', {
    mixins: [Shopware.Mixin.getByName('contextNodeMixin')],

    provide() {
        return {
            closeSubMenu: this.closeSubMenu,
            showSubMenu: this.showSubMenu
        };
    },

    data() {
        return {
            subMenu: null,
            depth: 0,
            rootObserver: null,
            width: 0
        };
    },

    computed: {
        subMenuRoot() {
            if (!this.subMenu) {
                return null;
            }

            // if this is the direct child
            if (this.subMenu.depth === 1) {
                return this.subMenu;
            }

            // search for toplevel buttons only
            let topLevelButton = this.subMenu;
            while (topLevelButton.depth > 2) {
                topLevelButton = topLevelButton.parentNode;
            }

            return topLevelButton.collapsed ? topLevelButton.parentNode : topLevelButton;
        },

        collapsed() {
            return this.width < 400;
        }
    },

    mounted() {
        this.registerObserver();
    },

    unmounted() {
        this.unregisterObserver();
    },

    render(createElement) {
        return createElement(
            'div', {
                class: 'sw-meteor-page-context'
            }, [
                createElement('sw-meteor-page-context-item', {
                    props: {
                        label: this.$tc('sw-saas-rufus.component.sw-meteor-page-context.labelButtonMore'),
                        icon: 'default-action-more-horizontal',
                        priority: 'auto'
                    },

                    scopedSlots: {
                        default: () => {
                            return typeof this.$scopedSlots.default === 'function' ?
                                this.$scopedSlots.default() : null;
                        }
                    }
                }),
                this.renderButtonBar()
            ]
        );
    },

    methods: {
        registerObserver() {
            this.rootObserver = new ResizeObserver(this.getWidth);
            this.rootObserver.observe(this.$el);
        },

        unregisterObserver() {
            if (this.rootObserver) {
                this.rootObserver.unobserve(this.$el);
                this.rootObserver = null;
            }
        },

        getWidth(resizeEntries) {
            if (resizeEntries.length <= 0) {
                return;
            }

            const resizeEntry = resizeEntries[0];

            this.width = resizeEntry.contentRect.width;
        },

        closeSubMenu() {
            this.subMenu = null;
        },

        showSubMenu(node, source) {
            if (source === this.subMenuRoot) {
                this.closeSubMenu();
                return;
            }

            this.subMenu = node;
        },

        renderButtonBar() {
            if (this.childNodes.length <= 0) {
                return null;
            }

            return this.childNodes.map((menuItem) => {
                return this.renderTopLevelButtons(menuItem);
            });
        },

        renderTopLevelButtons(menuItem) {
            const topLevelButtons = menuItem.renderedChildren.map((menuItemButton) => {
                return this.renderButton(menuItemButton);
            });

            if (menuItem.hasCollapsedChildren) {
                topLevelButtons.push(this.renderButton(menuItem));
            }

            return topLevelButtons;
        },

        renderButton(contextItem) {
            const elementContent = [];

            if (contextItem.icon) {
                elementContent.push(this.createIcon(
                    contextItem.icon,
                    ['sw-meteor-page-context-item__icon-action']
                ));
            }

            elementContent.push(this.$createElement(
                'span',
                {
                    class: 'sw-meteor-page-context-item__label'
                },
                contextItem.label
            ));

            if (contextItem.hasCollapsedChildren) {
                elementContent.push(this.createIcon(
                    'small-arrow-medium-down',
                    ['sw-meteor-page-context-item__icon-collapsed']
                ));
            }

            if (contextItem === this.subMenuRoot) {
                elementContent.push(this.$createElement('sw-meteor-page-context-menu', {
                    props: { menuEntry: this.subMenu }
                }));
            }

            return this.$createElement('span', {
                class: ['sw-meteor-page-context-item'],
                on: {
                    click: contextItem.onElementClick
                }
            }, elementContent);
        },

        createIcon(iconName, classes = []) {
            return this.$createElement(
                'sw-icon',
                {
                    class: classes,
                    props: { name: iconName, small: true }
                }
            );
        }
    }
});
