import template from './sw-product-variants-overview.html.twig';
import './sw-products-variants-overview.scss';

const { Component, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-variants-overview', {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
    ],

    props: {
        selectedGroups: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            sortBy: 'name',
            sortDirection: 'DESC',
            showDeleteModal: false,
            modalLoading: false,
            priceEdit: false,
            filterOptions: [],
            activeFilter: [],
            includeOptions: [],
            filterWindowOpen: false,
            toBeDeletedVariantId: null,
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'currencies',
            'taxes',
            'variants',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
            'defaultPrice',
            'defaultCurrency',
            'productTaxRate',
        ]),

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productMediaRepository() {
            return this.repositoryFactory.create(this.product.media.entity);
        },

        variantColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-product.variations.generatedListColumnVariation'),
                    allowResize: true,
                },
                ...this.currencyColumns,
                {
                    property: 'stock',
                    label: this.$tc('sw-product.variations.generatedListColumnStock'),
                    allowResize: true,
                    inlineEdit: 'number',
                    width: '125px',
                    align: 'right',
                },
                {
                    property: 'productNumber',
                    label: this.$tc('sw-product.variations.generatedListColumnProductNumber'),
                    allowResize: true,
                    inlineEdit: 'string',
                    width: '150px',
                },
                {
                    property: 'media',
                    label: this.$tc('sw-product.detailBase.cardTitleMedia'),
                    allowResize: true,
                    inlineEdit: true,
                    sortable: false,
                },
                {
                    property: 'active',
                    label: this.$tc('sw-product.variations.generatedListColumnActive'),
                    allowResize: true,
                    inlineEdit: 'boolean',
                    align: 'center',
                },
            ];
        },

        currencyColumns() {
            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            return this.currencies.sort((_a, b) => {
                return b.isSystemDefault ? 1 : -1;
            }).map((currency) => {
                return {
                    property: `price.${currency.id}.net`,
                    label: currency.translated.name || currency.name,
                    visible: currency.isSystemDefault,
                    allowResize: true,
                    primary: false,
                    rawData: false,
                    inlineEdit: 'number',
                    width: '250px',
                };
            });
        },

        canBeDeletedCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('canonicalProductId', this.toBeDeletedVariantId));

            return criteria;
        },
    },

    watch: {
        'selectedGroups'() {
            this.getFilterOptions();
        },
    },

    methods: {
        getList() {
            // Promise needed for inline edit error handling
            return new Promise((resolve) => {
                Shopware.State.commit('swProductDetail/setLoading', ['variants', true]);

                // Get criteria for search and for option sorting
                const searchCriteria = new Criteria();

                // Criteria for Search
                searchCriteria.setTotalCountMode(1);
                searchCriteria
                    .setPage(this.page)
                    .setLimit(this.limit)
                    .addFilter(Criteria.equals('product.parentId', this.product.id));

                searchCriteria.addAssociation('media');

                searchCriteria.getAssociation('options')
                    .addSorting(Criteria.sort('groupId'))
                    .addSorting(Criteria.sort('id'));

                // Add search term
                this.buildSearchQuery(searchCriteria);

                // User selected filters
                if (this.getFilterCriteria()) {
                    this.getFilterCriteria().forEach((criteria) => {
                        searchCriteria.addFilter(criteria);
                    });
                }

                // check for other sort values
                if (this.sortBy === 'name') {
                    searchCriteria
                        .addSorting(Criteria.sort('product.options.groupId', this.sortDirection))
                        .addSorting(Criteria.sort('product.options.id', this.sortDirection));
                } else {
                    searchCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
                }

                // Start search
                this.productRepository
                    .search(searchCriteria)
                    .then((res) => {
                        this.total = res.total;
                        Shopware.State.commit('swProductDetail/setVariants', res);
                        Shopware.State.commit('swProductDetail/setLoading', ['variants', false]);
                        this.$emit('variants-finish-update', this.variants);
                        resolve();
                    });
            });
        },

        buildSearchQuery(criteria) {
            if (!this.term) {
                return criteria;
            }

            // Split each word for search
            const terms = this.term.split(' ');

            // Create query for each single word
            terms.forEach((term) => {
                criteria.addQuery(Criteria.equals('product.options.name', term), 3500);
                criteria.addQuery(Criteria.contains('product.options.name', term), 500);
            });

            // return the input
            return criteria;
        },

        getFilterOptions() {
            // Prepare groups
            const groups = [...this.selectedGroups]
                .sort((a, b) => a.position - b.position).map((group, index) => {
                    const children = this.getOptionsForGroup(group.id);

                    return {
                        id: group.id,
                        name: group.name,
                        childCount: children.length,
                        parentId: null,
                        afterId: index > 0 ? this.selectedGroups[index - 1].id : null,
                        storeObject: group,
                    };
                });

            // Prepare options
            const children = groups.reduce((result, group) => {
                const options = this.getOptionsForGroup(group.id);

                // Iterate for each group options
                const optionsForGroup = options.sort((elementA, elementB) => {
                    return elementA.position - elementB.position;
                }).map((element, index) => {
                    const option = element.option;

                    // Get previous element
                    let afterId = null;
                    if (index > 0) {
                        afterId = options[index - 1].option.id;
                    }

                    return {
                        id: option.id,
                        name: option.name,
                        childCount: 0,
                        parentId: option.groupId,
                        afterId,
                        storeObject: element,
                    };
                });

                return [...result, ...optionsForGroup];
            }, []);

            // Assign groups and children to order objects
            this.filterOptions = [...groups, ...children];
        },

        resetFilterOptions() {
            this.filterOptions = [];
            this.includeOptions = [];

            this.$nextTick(() => {
                this.getFilterOptions();
                this.getList();
            });
        },

        filterOptionChecked(option) {
            if (option.checked) {
                // Remove from include list
                this.includeOptions.push({
                    id: option.id,
                    groupId: option.parentId,
                });
            } else {
                // Add to include option list
                this.includeOptions = this.includeOptions.filter((includeOption) => includeOption.id !== option.id);
            }
        },

        getFilterCriteria() {
            if (this.includeOptions.length <= 0) {
                return false;
            }

            // Collect each selected option in a group
            // [
            //   {id: 'abc123', options: [...optionIds]},
            //   {id: 'def456', options: [...optionIds]},
            // ]
            const optionInGroups = this.includeOptions.reduce((result, option) => {
                const parentGroup = result.find((group) => group.id === option.groupId);

                // Push to group when array exists
                if (parentGroup) {
                    parentGroup.options.push(option.id);
                } else {
                    // otherwise create new group with the option
                    result.push({
                        id: option.groupId,
                        options: [option.id],
                    });
                }

                return result;
            }, []);

            return optionInGroups.map((group) => {
                return Criteria.equalsAny('product.optionIds', group.options);
            });
        },

        getOptionsForGroup(groupId) {
            return this.product.configuratorSettings.filter((element) => {
                return !element.isDeleted && element.option.groupId === groupId;
            });
        },

        isPriceFieldInherited(variant, currency) {
            if (!variant.price) {
                return true;
            }

            const foundVariant = variant.price.find((price) => {
                return price.currencyId === currency.id;
            });

            return !foundVariant;
        },

        isActiveFieldInherited(variant) {
            return variant.active === null;
        },

        isMediaFieldInherited(variant) {
            if (variant.forceMediaInheritanceRemove) {
                return false;
            }

            if (variant.media) {
                return variant.media.length <= 0;
            }

            return !!variant.media;
        },

        onInheritanceRestore(variant, currency) {
            if (!variant.price) {
                return;
            }

            const foundVariantIndex = variant.price.findIndex((price) => {
                return price.currencyId === currency.id;
            });

            if (foundVariantIndex >= 0) {
                this.$delete(variant.price, foundVariantIndex);
            }

            if (variant.price.length <= 0) {
                variant.price = null;
            }
        },

        onActiveInheritanceRestore(variant) {
            variant.active = null;
        },

        onActiveInheritanceRemove(variant) {
            variant.active = true;
        },

        onInheritanceRemove(variant, currency) {
            if (!variant.price) {
                variant.price = [];
            }

            // remove inheritance on default currency variant
            if (!currency.isSystemDefault) {
                this.onInheritanceRemove(variant, this.defaultCurrency);
            }

            // create new price for selected currency
            const defaultPrice = this.getDefaultPriceForVariant(variant);
            const newPrice = {
                currencyId: currency.id,
                gross: defaultPrice.gross * currency.factor,
                linked: defaultPrice.linked,
                net: defaultPrice.net * currency.factor,
            };

            // add new price currency to variant
            this.$set(variant.price, variant.price.length, newPrice);
        },

        onMediaInheritanceRestore(variant, isInlineEdit) {
            if (!isInlineEdit) {
                return;
            }

            variant.forceMediaInheritanceRemove = false;
            variant.coverId = null;

            variant.media.getIds().forEach((mediaId) => {
                variant.media.remove(mediaId);
            });
        },

        onMediaInheritanceRemove(variant, isInlineEdit) {
            if (!isInlineEdit) {
                return;
            }

            variant.forceMediaInheritanceRemove = true;
            this.product.media.forEach(({ id, mediaId, position }) => {
                const media = this.productMediaRepository.create(Context.api);
                Object.assign(media, { mediaId, position, productId: this.product.id });
                if (this.product.coverId === id) {
                    variant.coverId = media.id;
                }

                variant.media.push(media);
            });
        },

        getDefaultPriceForVariant(variant) {
            if (!variant.price) {
                return this.defaultPrice;
            }

            const foundDefaultPrice = variant.price.find((price) => {
                return price.currencyId === this.defaultCurrency.id;
            });

            return foundDefaultPrice || this.defaultPrice;
        },

        onVariationDelete(item) {
            this.showDeleteModal = item.id;
        },

        onInlineEditSave(variation) {
            // check for changes
            if (!this.productRepository.hasChanges(variation)) {
                return;
            }

            // get product name
            const productName = variation.options.reduce((acc, option, index) => {
                return `${acc}${index > 0 ? ' - ' : ''}${option.translated.name}`;
            }, '');

            this.productRepository.save(variation).then(() => {
                // create success notification
                const titleSaveSuccess = this.$tc('global.default.success');
                const messageSaveSuccess = this.$tc('sw-product.detail.messageSaveSuccess', 0, {
                    name: productName,
                });

                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess,
                });

                // update items
                this.getList();
            }).catch(() => {
                // create error notification
                const titleSaveError = this.$tc('global.default.error');
                const messageSaveError = this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid');

                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError,
                });
            });
        },

        onInlineEditCancel() {
            this.getList();
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(item) {
            this.modalLoading = true;
            this.showDeleteModal = false;
            this.toBeDeletedVariantId = item.id;

            this.canVariantBeDeleted(item.id).then(canBeDeleted => {
                if (!canBeDeleted) {
                    this.modalLoading = false;
                    this.toBeDeletedVariantId = null;

                    this.createNotificationError({
                        message: this.$tc('sw-product.variations.generatedListMessageDeleteErrorCanonicalUrl'),
                    });

                    return;
                }

                this.productRepository.delete(item.id).then(() => {
                    this.modalLoading = false;
                    this.toBeDeletedVariantId = null;

                    this.createNotificationSuccess({
                        message: this.$tc('sw-product.variations.generatedListMessageDeleteSuccess'),
                    });

                    this.getList();
                });
            });
        },

        async canVariantBeDeleted() {
            const products = await this.productRepository.search(this.canBeDeletedCriteria);

            return products.length === 0;
        },

        onOptionEdit(variant) {
            if (variant?.id) {
                this.$router.push({
                    name: 'sw.product.detail',
                    params: {
                        id: variant.id,
                    },
                });
            }
        },

        isPriceEditing(value) {
            this.priceEdit = value;
        },
    },
});
