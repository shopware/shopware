import { Component } from 'src/core/shopware';
import template from './sw-product-modal-variant-generation.html.twig';
import VariantsGenerator from '../../../helper/sw-products-variants-generator';
import './sw-product-modal-variant-generation.scss';

Component.register('sw-product-modal-variant-generation', {
    template,

    inject: ['repositoryFactory', 'context'],

    props: {
        product: {
            type: Object,
            required: true
        },

        groups: {
            type: Array,
            required: true
        },

        selectedGroups: {
            type: Array,
            required: true
        }
    },

    data() {
        return {
            activeTab: 'options',
            isLoading: false,
            actualProgress: 0,
            maxProgress: 0,
            warningModal: false,
            warningModalNumber: 0,
            progressType: '',
            variantsNumber: 0,
            variantsGenerator: new VariantsGenerator(this.product)
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        configuratorSettingsRepository() {
            // get configuratorSettingsRepository
            return this.repositoryFactory.create(
                this.product.configuratorSettings.entity,
                this.product.configuratorSettings.source
            );
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        progressInPercentage() {
            return this.actualProgress / this.maxProgress * 100;
        },

        progressMessage() {
            if (this.progressType === 'delete') {
                return this.$tc('sw-product.variations.progressTypeDeleted');
            }
            if (this.progressType === 'upsert') {
                return this.$tc('sw-product.variations.progressTypeGenerated');
            }
            if (this.progressType === 'calc') {
                return this.$tc('sw-product.variations.progressTypeCalculated');
            }
            return '';
        }
    },

    methods: {
        createdComponent() {
            this.variantsGenerator.on('warning', (number) => {
                this.warningModalNumber = number;
                this.warningModal = true;
                this.isLoading = false;
            });

            this.variantsGenerator.on('progress-max', (maxProgress) => {
                this.maxProgress = maxProgress.progress;
                this.progressType = maxProgress.type;
            });

            this.variantsGenerator.on('progress-actual', (actualProgress) => {
                this.actualProgress = actualProgress.progress;
                this.progressType = actualProgress.type;
            });
        },

        generateVariants(forceGenerating) {
            this.isLoading = true;

            this.variantsGenerator.createNewVariants(forceGenerating, this.product.variantRestrictions).then(() => {
                // Save the product after generating
                this.productRepository.save(this.product, this.context).then(() => {
                    this.$emit('variations-generated');
                    this.$emit('modal-close');
                    this.isLoading = false;
                    this.actualProgress = 0;
                    this.maxProgress = 0;
                });
            }, () => {
                // When rejected
                this.isLoading = false;
            });
        },

        calcVariantsNumber() {
            // Group all option ids
            const groupedData = Object.values(this.product.configuratorSettings.items).reduce((accumulator, element) => {
                const groupId = element.option.groupId;
                const grouped = accumulator[groupId];

                if (grouped) {
                    grouped.push(element.option.id);
                    return accumulator;
                }

                accumulator[groupId] = [element.option.id];
                return accumulator;
            }, {});

            // Get only the values
            const groupedDataValues = Object.values(groupedData);

            // Multiply each group options when options are selected
            this.variantsNumber = groupedDataValues.length > 0
                ? groupedDataValues.map((group) => group.length)
                    .reduce((curr, length) => curr * length)
                : 0;
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
