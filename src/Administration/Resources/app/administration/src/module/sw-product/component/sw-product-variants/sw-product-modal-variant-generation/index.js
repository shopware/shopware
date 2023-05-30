/*
 * @package inventory
 */

import template from './sw-product-modal-variant-generation.html.twig';
import VariantsGenerator from '../../../helper/sw-products-variants-generator';
import './sw-product-modal-variant-generation.scss';

const { Criteria } = Shopware.Data;
const { Mixin, Context } = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
    ],

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

        actualStatus: {
            type: String,
            default: 'is-physical',
            required: false,
        },
    },

    data() {
        return {
            activeTab: 'options',
            isLoading: false,
            actualProgress: 0,
            maxProgress: 0,
            progressType: '',
            variantsNumber: 0,
            variantsGenerator: null,
            showUploadModal: false,
            variantGenerationQueue: { createQueue: [], deleteQueue: [] },
            term: '',
            paginatedVariantArray: [],
            disableRouteParams: true,
            downloadFilesForAllVariants: [],
            usageOfFiles: {},
            idToIndex: {},
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'currencies',
        ]),

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        optionRepository() {
            return this.repositoryFactory.create('property_group_option');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        progressInPercentage() {
            return this.actualProgress / (this.maxProgress * 100);
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

        buttonVariant() {
            if (this.variantsNumber <= 0) {
                return 'danger';
            }
            return 'primary';
        },

        buttonLabel() {
            if (this.variantsNumber <= 0) {
                return this.$tc('sw-product.variations.deleteVariationsButton');
            }

            return this.$tc('sw-product.variations.generateVariationsButton');
        },

        isGenerateButtonDisabled() {
            return this.variantGenerationQueue.createQueue.some((item) => {
                return item.downloads.length === 0 && item.productStates.includes('is-download');
            });
        },

    },

    watch: {
        variantGenerationQueue() {
            this.getList();
            this.showUploadModal = true;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.variantsGenerator = new VariantsGenerator();
            this.term = '';

            this.variantsGenerator.on('queues', (queues) => {
                const optionIdsToSearch = this.product.configuratorSettings.reduce((result, element) => {
                    if (result.indexOf(element.option.id) < 0) {
                        result.push(element.option.id);
                    }

                    return result;
                }, []);

                if (optionIdsToSearch.length > 0) {
                    const criteria = new Criteria(1, 500);
                    criteria.addFilter(Criteria.equalsAny('id', optionIdsToSearch));
                    criteria.addAssociation('group');

                    this.optionRepository.search(criteria).then((options) => {
                        queues.createQueue.forEach((item, index) => {
                            item.options.forEach((option) => {
                                option.entity = options.get(option.id);
                            });

                            item.options.sort((firstOption, secondOption) => {
                                const firstGroupName = firstOption.entity.group.name;
                                const secondGroupName = secondOption.entity.group.name;

                                return firstGroupName.localeCompare(secondGroupName);
                            });

                            item.downloads = [];
                            item.productStates = [];
                            item.id = item.productNumber;
                            this.idToIndex[item.id] = index;
                        });

                        this.variantGenerationQueue = queues;
                        this.total = queues.createQueue.length;
                    });
                } else {
                    this.variantGenerationQueue = queues;
                    this.total = queues.createQueue.length;
                }
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

        /**
         *
         * @param fileName string must include filename and its extension: "example.png"
         * @param variant
         */
        removeFile(fileName, variant) {
            // keep all downloadable files expect the one that should be removed
            variant.downloads = variant.downloads
                .filter(download => `${download.fileName}.${download.fileExtension}` !== fileName);

            // check if file is used in another place
            this.usageOfFiles[fileName] = this.usageOfFiles[fileName].filter(id => id !== variant.id);
            if (this.usageOfFiles[fileName].length === 0) {
                delete this.usageOfFiles[fileName];
            }
            const fileUsedElsewhere = this.usageOfFiles[fileName];

            if (!fileUsedElsewhere) {
                // removes file from the array, so it won't be shown inside the top upload component
                this.downloadFilesForAllVariants = this.downloadFilesForAllVariants
                    .filter(download => `${download.fileName}.${download.fileExtension}` !== fileName);
            }
        },

        removeFileForAllVariants(file) {
            const fileName = `${file.fileName}.${file.fileExtension}`;
            const usage = this.usageOfFiles[fileName];

            if (usage) {
                usage.forEach((use) => {
                    const index = this.idToIndex[use];
                    const variant = this.variantGenerationQueue.createQueue[index];
                    variant.downloads = variant.downloads
                        .filter(download => `${download.fileName}.${download.fileExtension}` !== fileName);
                });
                delete this.usageOfFiles[fileName];
            }

            this.downloadFilesForAllVariants = this.downloadFilesForAllVariants
                .filter(download => download.id !== file.id);
        },

        getList() {
            if (!this.variantGenerationQueue) {
                this.paginatedVariantArray = [];

                return;
            }

            const filteredQueue = [];
            this.variantGenerationQueue.createQueue.forEach((item) => {
                item.options.some((option) => {
                    const name = option.entity.translated?.name || option.entity.name;
                    if (name.toUpperCase().includes(this.term.toUpperCase()) || this.term === '') {
                        filteredQueue.push(item);
                        return true;
                    }

                    return false;
                });
            });

            const start = this.page * this.limit - this.limit;
            const end = start + this.limit;
            this.total = filteredQueue.length;
            this.paginatedVariantArray = filteredQueue.slice(start, end);
        },

        handlePageChange(opts) {
            this.onPageChange(opts);
            this.getList();
        },

        generateVariants() {
            this.isLoading = true;

            this.variantGenerationQueue.createQueue.forEach((item) => {
                delete item.id;

                if (item.productStates.includes('is-download')) {
                    item.maxPurchase = 1;
                    item.minPurchase = 1;
                    item.isCloseout = false;
                    item.shippingFree = false;
                }

                const mediaIds = [];
                item.downloads.forEach((download) => {
                    mediaIds.push({ mediaId: download.id });
                });

                item.downloads = mediaIds;
            });

            this.variantsGenerator.saveVariants(this.variantGenerationQueue).then(() => {
                return this.productRepository.save(this.product);
            }).then(() => {
                this.$emit('variations-finish-generate');
                this.$emit('modal-close');
                this.isLoading = false;
                this.actualProgress = 0;
                this.maxProgress = 0;

                this.$root.$emit('product-reload');
            });
        },

        showNextStep() {
            this.isLoading = true;
            this.variantsGenerator.generateVariants(
                this.currencies,
                this.product,
            );
            this.isLoading = false;
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

        onChangeAllVariantValues(checked) {
            let variants = this.variantGenerationQueue.createQueue;
            if (this.term) {
                variants = this.paginatedVariantArray;
            }

            if (!checked) {
                this.usageOfFiles = {};
                variants.forEach((item) => {
                    item.downloads = [];
                    item.productStates = [];
                });
                this.getList();
                return;
            }

            variants.forEach((item) => {
                item.downloads = [...this.downloadFilesForAllVariants];
                this.updateUsageForAllVariantFiles(item.id);

                item.productStates = ['is-download'];
            });

            this.getList();
        },

        onChangeVariantValue(checked, item) {
            if (!checked) {
                Object.keys(this.usageOfFiles).forEach((key) => {
                    this.usageOfFiles[key] = this.usageOfFiles[key].filter(id => id !== item.id);
                    if (this.usageOfFiles[key].length === 0) {
                        delete this.usageOfFiles[key];
                        this.downloadFilesForAllVariants = this.downloadFilesForAllVariants
                            .filter(download => `${download.fileName}.${download.fileExtension}` !== key);
                    }
                });

                item.downloads = [];
                item.productStates = [];
                return;
            }

            item.downloads = [...this.downloadFilesForAllVariants];
            this.updateUsageForAllVariantFiles(item.id);

            item.productStates = ['is-download'];
        },

        isUploadDisabled(item) {
            return item.downloads.length === 0;
        },

        isExistingMedia(files, targetId) {
            return files.some(({ id }) => {
                return id === targetId;
            });
        },

        successfulUpload(event, item) {
            this.mediaRepository.get(event.targetId, Context.api).then((media) => {
                if (item) {
                    if (this.isExistingMedia(item.downloads, event.targetId)) {
                        return;
                    }

                    item.downloads.push(media);
                    this.pushFileToUsageList(`${media.fileName}.${media.fileExtension}`, item.id);
                    return;
                }

                if (!this.isExistingMedia(this.downloadFilesForAllVariants, event.targetId)) {
                    this.downloadFilesForAllVariants.push(media);
                }

                let variants = this.variantGenerationQueue.createQueue;
                if (this.term) {
                    variants = this.paginatedVariantArray;
                }

                variants.forEach((currentItem) => {
                    if (currentItem.productStates.includes('is-download')) {
                        if (this.isExistingMedia(currentItem.downloads, event.targetId)) {
                            return;
                        }

                        currentItem.downloads.push(media);

                        this.pushFileToUsageList(`${media.fileName}.${media.fileExtension}`, currentItem.id);
                    }
                });
            });
        },

        updateUsageForAllVariantFiles(id) {
            this.downloadFilesForAllVariants.forEach((download) => {
                this.pushFileToUsageList(`${download.fileName}.${download.fileExtension}`, id);
            });
        },

        pushFileToUsageList(fileName, id) {
            if (!this.usageOfFiles[fileName]) {
                this.usageOfFiles[fileName] = [];
            }
            this.usageOfFiles[fileName].push(id);
        },

        onTermChange(term) {
            this.term = term;
            this.getList();
        },
    },
};
