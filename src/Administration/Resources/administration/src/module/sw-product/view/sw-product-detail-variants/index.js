import { Application, Component, State } from 'src/core/shopware';
import EntityStore from 'src/core/data/EntityStore';
import template from './sw-product-detail-variants.html.twig';
import './sw-product-detail-variants.scss';

Component.register('sw-product-detail-variants', {
    template,

    data() {
        return {
            variantTotal: 0,
            languageId: null
        };
    },

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        configuratorStore() {
            return this.product.getAssociation('configurators');
        },

        groupStore() {
            return State.getStore('configuration_group');
        },

        variantStore() {
            return this.product.getAssociation('children');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const params = { page: 1, limit: 500 };

            this.configuratorStore.getList(params);
        },

        generateVariants() {
            this.createNewVariants().then(() => {
                this.variantStore.getList();
            });
        },

        createNewVariants() {
            return new Promise((resolve) => {
                const grouped = this.groupOptions(this.configuratorStore);

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

        groupOptions(configurators) {
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
        }
    }
});
