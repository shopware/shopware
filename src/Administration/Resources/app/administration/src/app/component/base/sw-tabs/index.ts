import type { PropType } from 'vue';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/components/navigation/mt-tabs/mt-tabs';
import template from './sw-tabs.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-tabs and mt-tabs. Autoswitches between the two components.
 */
Component.register('sw-tabs', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        /**
         * Only used for new mt-tabs component
         */
        items: {
            type: Array as PropType<TabItem[]>,
            required: false,
        },
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-tabs',
                // eslint-disable-next-line max-len
                'The old usage of "sw-tabs" is deprecated and will be removed in v6.7.0.0. Please use "mt-tabs" instead.',
            );

            return false;
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
                                // eslint-disable-next-line max-len
                                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-explicit-any
                                .map((child: any) => {
                                    return {
                                        // eslint-disable-next-line max-len
                                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                                        label: child.props?.title ?? child.props?.name,
                                        // eslint-disable-next-line max-len
                                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                                        name: child.props?.name ?? child.props?.title,
                                        onClick: () => {
                                            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                                            if (child.props?.route) {
                                                // eslint-disable-next-line max-len
                                                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
                                                void this.$router.push(child.props.route);
                                            }
                                        },
                                    };
                                })
                        );
                    }

                    // eslint-disable-next-line max-len
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

        // eslint-disable-next-line @typescript-eslint/ban-types
        listeners(): Record<string, Function | Function[]> {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
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
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            this.activeItem = this.itemsBackwardCompatible[0].name;
        }
    },

    methods: {
        getSlots() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return {
                    ...this.$slots,
                    ...this.$scopedSlots,
                };
            }

            return this.$slots;
        },

        mountedComponent() {
            // Fallback for $refs access in some modules
            if (this.$refs.tabComponent) {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.$refs.tabComponent.mountedComponent();
            }
        },

        setActiveItem(item: unknown) {
            // Fallback for $refs access in some modules
            if (this.$refs.tabComponent) {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.$refs.tabComponent.setActiveItem(item);
            }
        },

        onNewItemActive(item: unknown) {
            this.$emit('new-item-active', item);
            this.activeItem = item;
        },
    },
});
