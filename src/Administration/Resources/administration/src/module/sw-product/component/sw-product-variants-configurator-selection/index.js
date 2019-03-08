import { Component, Application, State, Mixin } from 'src/core/shopware';
import EntityStore from 'src/core/data/EntityStore';
import StoreLoader from 'src/core/helper/store-loader.helper';
import template from './sw-product-variants-configurator-selection.html.twig';
import VariantsGenerator from '../../helper/sw-products-variants-generator';
import './sw-product-variants-configurator-selection.scss';

Component.extend('sw-product-variants-configurator-selection', 'sw-property-search', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    },

    data() {
        return {
            isLoading: false,
            actualProgress: 0,
            maxProgress: 0,
            warningModal: false,
            warningModalNumber: 0,
            progressType: ''
        };
    },

    created() {
        this.createdComponent();
        this.createdComponentHookAfter();
    },

    computed: {
        variantsGenerator() {
            const variantsGeneratorDependencies = {
                product: this.product,
                syncService: Application.getContainer('service').syncService,
                EntityStore: EntityStore,
                State: State,
                $tc: this.$tc
            };

            return new VariantsGenerator(variantsGeneratorDependencies);
        },

        configuratorStore() {
            return this.product.getAssociation('configurators');
        },

        progressInPercentage() {
            return this.actualProgress / this.maxProgress * 100;
        },

        progressMessage() {
            if (this.progressType === 'delete') {
                return this.$tc('sw-product.variations.progressTypeDeleted');
            }
            if (this.progressType === 'create') {
                return this.$tc('sw-product.variations.progressTypeGenerated');
            }
            return '';
        }
    },

    methods: {
        createdComponentHookAfter() {
            const loader = new StoreLoader();
            loader.loadAll(this.configuratorStore);

            this.variantsGenerator.on('warning', (number) => {
                this.warningModalNumber = number;
                this.warningModal = true;
                this.isLoading = false;
            });

            this.variantsGenerator.on('maxProgressChange', (maxProgress) => {
                this.maxProgress = maxProgress.progress;
                this.progressType = maxProgress.type;
            });

            this.variantsGenerator.on('actualProgressChange', (actualProgress) => {
                this.actualProgress = actualProgress.progress;
                this.progressType = actualProgress.type;
            });
        },

        generateVariants(forceGenerating) {
            this.isLoading = true;

            this.variantsGenerator.createNewVariants(forceGenerating).then(() => {
                this.product.save();
                this.$emit('variationsGenerated');
                this.isLoading = false;
                this.actualProgress = 0;
                this.maxProgress = 0;
            }, () => {
                // todo: add error handling
                this.isLoading = false;
            });
        },

        addOptionCount() {
            const options = Object.values(this.options.store);

            this.groups.forEach((group) => {
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

            if (exists && exists.isDeleted) {
                exists.isDeleted = false;
                this.addOptionCount();
                return;
            } if (exists !== null) {
                exists.delete();
                this.addOptionCount();
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
        },

        onConfirmWarningModal() {
            this.warningModal = false;
            this.generateVariants(true);
        },

        onCloseWarningModal() {
            this.warningModal = false;
            this.isLoading = false;
        }
    }
});
