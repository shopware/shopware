import template from './sw-product-modal-variant-generation.html.twig';
import VariantsGenerator from '../../../helper/sw-products-variants-generator';
import './sw-product-modal-variant-generation.scss';

const { Component } = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-product-modal-variant-generation', {
    template,

    inject: ['repositoryFactory'],

    props: {
        product: {
            type: Object,
            required: true,
        },

        groups: {
            type: Array,
            required: true,
        },

        selectedGroups: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            activeTab: 'options',
            isLoading: false,
            actualProgress: 0,
            maxProgress: 0,
            notificationModal: false,
            notificationInfos: {},
            progressType: '',
            variantsNumber: 0,
            variantsGenerator: new VariantsGenerator(),
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'currencies',
        ]),

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
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.variantsGenerator.on('notification', (notificationInfos) => {
                this.notificationInfos = notificationInfos;
                this.notificationModal = true;
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

            this.variantsGenerator.createNewVariants(forceGenerating, this.currencies, this.product).then(() => {
                // Save the product after generating
                this.productRepository.save(this.product).then(() => {
                    this.$emit('variations-finish-generate');
                    this.$emit('modal-close');
                    this.isLoading = false;
                    this.actualProgress = 0;
                    this.maxProgress = 0;

                    this.$root.$emit('product-reload');
                });
            }, () => {
                // When rejected
                this.isLoading = false;
            });
        },

        calcVariantsNumber() {
            // Group all option ids
            const groupedData = this.product.configuratorSettings.reduce((accumulator, element) => {
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

        onConfirmNotificationModal() {
            this.notificationModal = false;
            this.generateVariants(true);
        },

        onCloseNotificationModal() {
            this.notificationModal = false;
            this.isLoading = false;
        },
    },
});
