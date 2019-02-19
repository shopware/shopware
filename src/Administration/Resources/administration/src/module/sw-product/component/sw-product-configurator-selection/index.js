import { Component } from 'src/core/shopware';

Component.extend('sw-product-configurator-selection', 'sw-property-search', {
    methods: {
        addOptionCount() {
            this.groups.forEach((group) => {
                const options = Object.values(this.options.store);

                const optionCount = options.filter((configurator) => {
                    let option = configurator.option;

                    if (configurator.internalOption) {
                        option = configurator.internalOption;
                    }

                    return option.groupId === group.id && !configurator.isDeleted;
                });

                this.$set(group, 'optionCount', optionCount.length);
            });
        },

        selectOptions(grid) {
            grid.selectAll(false);

            this.preventSelection = true;
            this.options.forEach((configurator) => {
                let option = configurator.option;

                if (configurator.internalOption) {
                    option = configurator.internalOption;
                }

                if (option) {
                    grid.selectItem(!configurator.isDeleted, option);
                }
            });

            this.preventSelection = false;
        },

        onOptionSelect(selection, item) {
            if (this.preventSelection) {
                return;
            }

            const exists = this.findConfiguratorForOptionId(item.id);

            if (exists !== null) {
                exists.delete();
                return;
            }

            const newOption = this.options.create();
            newOption.setLocalData({
                optionId: item.id,
                internalOption: item
            });

            this.addOptionCount();
        },

        findConfiguratorForOptionId(optionId) {
            let found = null;

            this.options.forEach((item) => {
                if (optionId === item.optionId) {
                    found = item;
                }
            });

            return found;
        }
    }
});
