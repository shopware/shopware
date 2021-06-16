import template from './sw-product-variants-delivery-order.html.twig';
import './sw-product-variants-delivery-order.scss';

const { Component } = Shopware;

Component.register('sw-product-variants-delivery-order', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
        },

        selectedGroups: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            groups: [],
            orderObjects: [],
        };
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.createOrderObjects();
        },

        createOrderObjects() {
            // prepare group sorting
            let sortedGroups = [];
            const selectedGroupsCopy = [...this.selectedGroups];

            // check if sorting exists on server
            if (this.product.configuratorGroupConfig && this.product.configuratorGroupConfig.length > 0) {
                // add server sorting to the sortedGroups
                sortedGroups = this.product.configuratorGroupConfig.reduce((acc, configGroup) => {
                    const relatedGroup = selectedGroupsCopy.find(group => group.id === configGroup.id);

                    if (relatedGroup) {
                        acc.push(relatedGroup);

                        // remove from orignal array
                        selectedGroupsCopy.splice(selectedGroupsCopy.indexOf(relatedGroup), 1);
                    }

                    return acc;
                }, []);
            }

            // add non sorted groups at the end of the sorted array
            sortedGroups = [...sortedGroups, ...selectedGroupsCopy];

            // prepare groups
            const groups = sortedGroups.map((group, index) => {
                const children = this.getOptionsForGroup(group.id);

                return {
                    id: group.id,
                    name: group.translated.name,
                    childCount: children.length,
                    parentId: null,
                    afterId: index > 0 ? sortedGroups[index - 1].id : null,
                    storeObject: group,
                };
            });

            // prepare options
            const children = groups.reduce((result, group) => {
                const options = this.getOptionsForGroup(group.id);

                // iterate for each group options
                const optionsForGroup = options.sort((elementA, elementB) => {
                    return elementA.position - elementB.position;
                }).map((element, index) => {
                    const option = element.option;

                    // get previous element
                    let afterId = null;
                    if (index > 0) {
                        afterId = options[index - 1].option.id;
                    }

                    return {
                        id: option.id,
                        name: option.translated.name,
                        childCount: 0,
                        parentId: option.groupId,
                        afterId,
                        storeObject: element,
                    };
                });

                return [...result, ...optionsForGroup];
            }, []);

            // assign groups and children to order objects
            this.orderObjects = [...groups, ...children];
        },

        getOptionsForGroup(groupId) {
            return this.product.configuratorSettings.filter((element) => {
                return !element.isDeleted && element.option.groupId === groupId;
            });
        },

        orderChanged() {
            const groups = this.orderObjects.filter((object) => object.parentId === null);

            // when configuratorGroupConfig is null then add empty array
            if (!this.product.configuratorGroupConfig) {
                this.product.configuratorGroupConfig = [];
            }

            // get order from administration ui
            const orderedGroupIds = [];
            let latestGroup = groups.find(group => group.afterId === null);
            groups.forEach(() => {
                if (latestGroup !== undefined) {
                    orderedGroupIds.push(latestGroup.id);
                    latestGroup = groups.find(thisGroup => thisGroup.afterId === latestGroup.id);
                }
            });

            // create new groupConfig Objects in sorted order
            const newConfiguratorGroupConfig = [];
            orderedGroupIds.forEach((groupId) => {
                const foundGroup = this.product.configuratorGroupConfig.find((group) => group.id === groupId);

                // when group exists
                if (foundGroup) {
                    // add to newConfiguratorGroupConfig
                    newConfiguratorGroupConfig.push(foundGroup);
                } else {
                    // otherwise create new group
                    newConfiguratorGroupConfig.push({
                        id: groupId,
                        expressionForListings: false,
                        representation: 'box',
                    });
                }
            });

            // set new order
            this.product.configuratorGroupConfig = newConfiguratorGroupConfig;

            // Set option ordering
            const options = this.orderObjects.filter((object) => object.parentId);

            groups.forEach((group) => {
                const optionsForGroup = options.filter((option) => option.parentId === group.id);
                let latestOption = optionsForGroup.find(option => option.afterId === null);

                optionsForGroup.forEach((option, index) => {
                    if (latestOption !== undefined) {
                        latestOption.storeObject.position = index + 1;
                        latestOption = optionsForGroup.find(thisOption => thisOption.afterId === latestOption.id);
                    }
                });
            });
        },
    },
});
