import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import template from './sw-tabs.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @deprecated tag:v6.9.0 - Will be removed. Use `mt-tabs` instead.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        /**
         * Enables the temporary Meteor compatibility path.
         */
        useMeteorComponent: {
            type: Boolean,
            required: false,
            default: false,
        },

        /**
         * Only used for new mt-tabs component
         */
        items: {
            type: Array as PropType<TabItem[]>,
            required: false,
        },
    },

    computed: {
        shouldUseMeteorComponent() {
            return this.useMeteorComponent;
        },

        itemsBackwardCompatible(): TabItem[] {
            if (this.items) {
                return this.items;
            }

            const defaultSlotContent = this.$slots.default?.({});

            if (!defaultSlotContent) {
                return [];
            }

            /**
             * Iterate over the default slot content and extract the tab items
             * and convert them to the new format
             */
            let items = defaultSlotContent
                .filter((item) => {
                    return (
                        // @ts-expect-error
                        item.type?.name === 'sw-tabs-item' ||
                        // eslint-disable-next-line @typescript-eslint/no-base-to-string
                        item.type?.toString() === 'Symbol(v-fgt)'
                    );
                })
                .map((item) => {
                    // Handle fragments

                    // eslint-disable-next-line @typescript-eslint/no-base-to-string
                    if (item.type?.toString() === 'Symbol(v-fgt)') {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                        return (
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                            (item.children ?? [])
                                // @ts-expect-error
                                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                                ?.filter((child) => child.type?.name === 'sw-tabs-item')
                                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-explicit-any
                                .map((child: any) => {
                                    return {
                                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                                        label: child.props?.title ?? child.props?.name,
                                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                                        name: child.props?.name ?? child.props?.title,
                                        onClick: () => {
                                            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                                            if (child.props?.route) {
                                                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
                                                void this.$router.push(child.props.route);
                                            }
                                        },
                                    };
                                })
                        );
                    }

                    /* eslint-disable @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call */
                    let label = item.props?.title;
                    let name = item.props?.name ?? item.props?.title;

                    if (label === undefined) {
                        // @ts-expect-error
                        // Get label from default slot content of item
                        const defaultSlot = item.children?.default?.()?.[0];
                        // Check if default slot is Symbol(v-txt)
                        if (defaultSlot?.type?.toString() === 'Symbol(v-txt)') {
                            label = defaultSlot.children;
                        }
                    }

                    if (name === undefined) {
                        // Use label as name if name is not set
                        name = label;
                    }

                    /* eslint-enable @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access */

                    return {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        label,
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        name,
                        onClick: () => {
                            if (item.props?.route) {
                                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                                void this.$router.push(item.props.route);
                            }
                        },
                    };
                });

            // Flat map items
            items = items.flat();

            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return items;
        },
    },

    data(): {
        activeItem: unknown;
    } {
        return {
            activeItem: null,
        };
    },

    mounted() {
        // Set first item as active
        if (this.itemsBackwardCompatible.length > 0) {
            this.activeItem = this.itemsBackwardCompatible[0].name;
        }
    },

    methods: {
        getSlots() {
            return this.$slots;
        },

        mountedComponent() {
            // Fallback for $refs access in some modules
            if (this.$refs.tabComponent) {
                // @ts-expect-error
                this.$refs.tabComponent.mountedComponent();
            }
        },

        setActiveItem(item: unknown) {
            // Fallback for $refs access in some modules
            if (this.$refs.tabComponent) {
                // @ts-expect-error
                this.$refs.tabComponent.setActiveItem(item);
            }
        },

        onNewItemActive(item: unknown) {
            this.$emit('new-item-active', item);
            this.activeItem = item;
        },
    },
});
