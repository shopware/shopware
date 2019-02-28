import { Component, State, Application } from 'src/core/shopware';
import EntityStore from 'src/core/data/EntityStore';
import StoreLoader from 'src/core/helper/store-loader.helper';
import template from './sw-product-variants-configurator-selection.html.twig';

Component.extend('sw-product-variants-configurator-selection', 'sw-property-search', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    },

    data() {
        return {
            languageId: null
        };
    },

    computed: {
        configuratorStore() {
            return this.product.getAssociation('configurators');
        },

        groupStore() {
            return State.getStore('configuration_group');
        }
    },

    created() {
        this.createdComponent();
        this.createdComponentHookAfter();
    },

    methods: {
        createdComponentHookAfter() {
            const loader = new StoreLoader();

            loader.loadAll(this.configuratorStore);
        },

        generateVariants() {
            this.isLoading = true;
            this.createNewVariants().then(() => {
                this.product.save();
                this.$emit('variationsGenerated');
                this.isLoading = false;
            });
        },

        createNewVariants() {
            return new Promise((resolve) => {
                const grouped = this.groupTheOptions(this.configuratorStore);

                if (grouped.length <= 0) {
                    return;
                }

                const permutations = this.buildCombinations(grouped);
                const variants = [];

                // todo filter restrictions
                permutations.forEach((permutation) => {
                    const variations = permutation.map((optionId) => {
                        return { id: optionId };
                    });

                    // todo calculate price with surcharges
                    // todo consider product.priceRules (store of prices)

                    variants.push({
                        parentId: this.product.id,
                        variations: variations,
                        price: { gross: 200, net: 100, linked: false }
                    });
                });

                this.sendVariants(variants, 0, 50, resolve);
            });
        },

        sendVariants(variants, offset, limit, resolve) {
            const chunk = variants.slice(offset, offset + limit);

            if (chunk.length <= 0) {
                resolve();
                return;
            }

            const syncService = Application.getContainer('service').syncService;

            const payload = [{
                action: 'upsert',
                entity: 'product',
                payload: chunk
            }];

            const header = EntityStore.getLanguageHeader(this.getLanguageId());

            syncService.sync(payload, {}, header).then(() => {
                this.sendVariants(variants, offset + limit, limit, resolve);
            });
        },

        buildCombinations(data, group = [], val = null, i = 0) {
            const all = [];

            if (val !== null) {
                group.push(val);
            }

            if (i >= data.length) {
                all.push(group);
                return all;
            }

            data[i].forEach((v) => {
                const x = i + 1;
                const nested = this.buildCombinations(data, group.slice(), v, x);

                nested.forEach((nestedItem) => {
                    all.push(nestedItem);
                });
            });

            return all;
        },

        groupTheOptions(configurators) {
            const groupedData = {};

            configurators.forEach((configurator) => {
                if (configurator.isDeleted) {
                    return;
                }

                let option = configurator.option;
                if (configurator.internalOption) {
                    option = configurator.internalOption;
                }

                const groupId = option.groupId;
                const grouped = groupedData[groupId];

                if (grouped) {
                    grouped.push(option.id);
                    // grouped.push(option.name);
                    return;
                }

                groupedData[groupId] = [option.id];
                // groupedData[groupId] = [option.name];
            });

            return Object.values(groupedData);
        },

        getLanguageId() {
            if (this.languageId === null) {
                const store = State.getStore('language');

                this.languageId = store.getCurrentId();
            }
            return this.languageId;
        },

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
