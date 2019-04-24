import { Component, Mixin } from 'src/core/shopware';
import template from './sw-product-variants-configurator-selection.html.twig';
import './sw-product-variants-configurator-selection.scss';

Component.extend('sw-product-variants-configurator-selection', 'sw-property-search', {
    template,

    inject: ['repositoryFactory', 'context'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        product: {
            type: Object,
            required: true
        }
    },

    computed: {
        configuratorSettingsRepository() {
            // get configuratorSettingsRepository
            return this.repositoryFactory.create(
                this.product.configuratorSettings.entity,
                this.product.configuratorSettings.source
            );
        }
    },

    created() {
        this.$super.createdComponent();
    },

    methods: {
        /**
         * Important: options = configurators
         * Reason: Is extended from sw-property-search
         */
        addOptionCount() {
            const optionStore = Object.values(this.options.items);

            this.groups.forEach((group) => {
                const optionCount = optionStore.filter((configurator) => {
                    // check if option belongs to group
                    return configurator.option.groupId === group.id && !configurator.isDeleted;
                });

                // set reactive
                this.$set(group, 'optionCount', optionCount.length);
            });

            this.$emit('optionSelect');
        },

        selectOptions(grid) {
            grid.selectAll(false);

            this.preventSelection = true;
            this.options.forEach((configurator) => {
                if (configurator.option) {
                    grid.selectItem(!configurator.isDeleted, configurator.option);
                }
            });

            this.preventSelection = false;
        },

        onOptionSelect(selection, item) {
            if (this.preventSelection) {
                return;
            }

            const exists = this.findConfiguratorForOptionId(item.id);

            if (exists) {
                this.options.remove(exists[0]);
                this.addOptionCount();
                return;
            }

            const newOption = this.configuratorSettingsRepository.create(this.context);
            newOption.optionId = item.id;
            newOption.option = item;

            this.options.add(newOption);

            this.addOptionCount();
        },

        findConfiguratorForOptionId(optionId) {
            let found = null;

            Object.entries(this.options.items).forEach((item) => {
                if (optionId === item[1].optionId) {
                    found = item;
                }
            });

            return found;
        }
    }
});
