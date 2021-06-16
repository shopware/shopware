import template from './sw-product-variant-modal.html.twig';
import './sw-product-variant-modal.scss';

const { Component, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-variant-modal', {
    template,

    inject: [
        'feature',
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        // this is the parent product entity from wich we will get all the variants
        productEntity: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            productVariants: [],
            currency: null,
            paginationLimit: 25,
            paginationPage: 1,
            toBeDeletedVariants: [],
            showDeleteModal: false,
            searchTerm: '',
            isDeleteButtonLoading: false,
            isDeletionOver: false,
            sortDirection: 'ASC',
            sortBy: 'productNumber',
        };
    },

    computed: {
        modalTitle() {
            return this.$t('sw-product.list.variantModalTitle', { productName: this.productEntity.translated.name });
        },

        openMainProductText() {
            return this.$t('sw-product.list.openMainProduct', { productName: this.productEntity.translated.name });
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productMediaRepository() {
            return this.repositoryFactory.create(this.productEntity.media.entity);
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        contextMenuEditText() {
            return this.acl.can('product.editor') ?
                this.$tc('global.default.edit') :
                this.$tc('global.default.view');
        },

        productVariantCriteria() {
            const criteria = new Criteria();

            // this is the id of the main product.
            const productEntityId = this.productEntity.id;
            criteria.addFilter(Criteria.equals('parentId', productEntityId));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            criteria.getAssociation('options')
                .addAssociation('group');
            criteria.addAssociation('cover');
            criteria.addAssociation('media');

            if (this.searchTerm) {
                // Split each word for search
                const terms = this.searchTerm.split(' ');

                // Create query for each single word
                terms.forEach(term => {
                    criteria.addQuery(Criteria.equals('product.options.name', term), 3500);
                    criteria.addQuery(Criteria.contains('product.options.name', term), 500);
                });
            }

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, true));

            criteria.setPage(this.paginationPage);
            criteria.setLimit(this.paginationLimit);

            return criteria;
        },

        gridColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: this.$tc('sw-product.list.columnName'),
                    routerLink: 'sw.product.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                },
                {
                    property: 'price',
                    dataIndex: `price.${this.currency?.id || ''}.net`,
                    label: 'sw-product.list.columnPrice',
                    allowResize: true,
                    width: '250px',
                    inlineEdit: 'number',
                    align: 'right',
                },
                {
                    property: 'stock',
                    dataIndex: 'stock',
                    label: 'sw-product.list.columnInStock',
                    allowResize: true,
                    inlineEdit: 'number',
                    align: 'right',
                },
                {
                    property: 'active',
                    dataIndex: 'active',
                    label: 'sw-product.list.columnActive',
                    allowResize: true,
                    inlineEdit: 'boolean',
                    align: 'center',
                },
                {
                    property: 'productNumber',
                    dataIndex: 'productNumber',
                    label: 'sw-product.list.columnProductNumber',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'media',
                    dataIndex: 'media',
                    label: this.$tc('sw-product.list.columnMedia'),
                    allowResize: true,
                    inlineEdit: true,
                    sortable: false,
                },
            ];
        },

        canBeDeletedCriteria() {
            const criteria = new Criteria();
            const variantIds = this.toBeDeletedVariants.map(variant => variant.id);
            criteria.addFilter(Criteria.equalsAny('canonicalProductId', variantIds));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchProductVariants();
            this.fetchSystemCurrency();
        },

        fetchSystemCurrency() {
            const systemCurrencyId = Shopware.Context.app.systemCurrencyId;

            this.currencyRepository.get(systemCurrencyId).then(response => {
                this.currency = response;
            });
        },

        fetchProductVariants() {
            this.productRepository.search(this.productVariantCriteria).then(response => {
                this.productVariants = response;
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

        onInheritanceRemove(variant, currency) {
            if (!variant.price) {
                variant.price = [];
            }

            // create new price for selected currency
            const defaultPrice = this.productEntity.price[0];
            const newPrice = {
                currencyId: currency.id,
                gross: defaultPrice.gross * currency.factor,
                linked: defaultPrice.linked,
                net: defaultPrice.net * currency.factor,
            };

            // add new price currency to variant
            this.$set(variant.price, variant.price.length, newPrice);
        },

        /**
         * Sorts variant options by their position. If the position is the same it sorts them by their name.
         * @param {Array} options
         * @return {Array}
         */
        sortOptions(options) {
            // making a local copy because using .sort directly on `option` causes an infinite loop
            const localOptions = [...options];

            return localOptions.sort((a, b) => {
                if (a.position === b.position) {
                    return a.name > b.name ? 1 : -1;
                }

                return a.position > b.position ? 1 : -1;
            });
        },

        /**
         * Returns a string with all options of an variant: "(color: black, size: xl)"
         * @param {object} variant
         * @returns {string}
         */
        buildVariantOptions(variant, seperator = ', ', ommitParenthesis = false, ommitOptionGroupName = false) {
            const options = variant.options;

            const sortedOptions = this.sortOptions(options);

            /* Creates following string: "color: black, size: xl".
             * The slice method removes the last two chars from the string wich are: ", ".
             */
            const formattedOptions = sortedOptions.reduce((accumulator, currentOption) => {
                const optionValue = currentOption.translated.name;
                const optionGroupName = currentOption.group.translated.name;

                return accumulator.concat(
                    !ommitOptionGroupName ? optionGroupName : '',
                    !ommitOptionGroupName ? ': ' : '',
                    optionValue,
                    seperator,
                );
            }, '').slice(0, -seperator.length);

            return ommitParenthesis ? formattedOptions : `(${formattedOptions})`;
        },

        /**
         * Returns a string with the name of the variant and the options: T-Shirt (black, xl)
         * @param {object} variant
         * @returns {string}
         */
        buildVariantName(variant) {
            const options = this.buildVariantOptions(variant);
            const variantName = variant.translated.name || this.productEntity.translated.name;

            return `${variantName} ${options}`;
        },

        /**
         * Returns the price of a variant. If the variant has no price it gets the price of the parent product.
         * @param {object} variant
         * @returns {number}
         */
        getVariantPrice(variant) {
            const variantPrice = variant.price;

            return variantPrice ? variantPrice[0] : this.productEntity.price[0];
        },

        onPageChange({ limit = 25, page = 1 }) {
            this.paginationLimit = limit;
            this.paginationPage = page;

            this.fetchProductVariants();
        },

        visitProduct(productId) {
            this.$emit('modal-close');

            // using $nextTick to wait unit the dom has updated and the modal is closed
            this.$nextTick().then(() => {
                this.$router.push({
                    name: 'sw.product.detail',
                    params: {
                        id: productId,
                    },
                });
            });
        },

        getItemMedia(item) {
            if (item.cover) {
                return item.cover.media;
            }

            if (this.productEntity.cover) {
                return this.productEntity.cover.media;
            }

            return null;
        },

        deleteVariants() {
            this.isDeleteButtonLoading = true;

            const variantIds = this.toBeDeletedVariants.map(variant => variant.id);
            const variantName = this.toBeDeletedVariants[0].translated.name || this.productEntity.translated.name;
            const amount = variantIds.length;

            this.canVariantsBeDeleted().then(canBeDeleted => {
                if (!canBeDeleted) {
                    this.isDeleteButtonLoading = false;
                    this.isDeletionOver = true;

                    this.createNotificationError({
                        message: this.$tc(
                            'sw-product.list.notificationVariantDeleteErrorCanonicalUrl',
                            amount,
                            { variantName },
                        ),
                    });

                    return;
                }

                this.productRepository.syncDeleted(variantIds)
                    .then(() => {
                        this.createNotificationSuccess({
                            message: this.$tc(
                                'sw-product.list.notificationVariantDeleteSuccess',
                                amount,
                                { variantName, amount },
                            ),
                        });

                        this.fetchProductVariants();
                    })
                    .catch(() => {
                        this.createNotificationError({
                            message: this.$tc(
                                'sw-product.list.notificationVariantDeleteError',
                                amount,
                                { variantName, amount },
                            ),
                        });
                    })
                    .finally(() => {
                        this.isDeleteButtonLoading = false;
                        this.isDeletionOver = true;
                    });
            });
        },

        async canVariantsBeDeleted() {
            const products = await this.productRepository.search(this.canBeDeletedCriteria);

            return products.length === 0;
        },

        onInlineEditSave(editedVariant) {
            const variantName = this.buildVariantName(editedVariant);

            this.productRepository.save(editedVariant).then(() => {
                this.createNotificationSuccess({
                    message: this.$t('sw-product.list.notificationVariantSaveSuccess', { variantName }),
                });

                this.fetchProductVariants();
            });
        },

        onInlineEditCancel() {
            this.fetchProductVariants();
        },

        onClickBulkDelete() {
            const gridSelection = this.$refs.variantGrid.selection;
            this.toBeDeletedVariants = Object.values(gridSelection);

            this.showDeleteModal = true;
        },

        closeDeleteModal() {
            this.showDeleteModal = false;
            this.toBeDeletedVariants = [];
            this.isDeletionOver = false;
        },

        onDeleteVariant(variant) {
            this.toBeDeletedVariants.push(variant);

            this.showDeleteModal = true;
        },

        onSearchTermChange() {
            this.fetchProductVariants();
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.dataIndex;
            }

            this.fetchProductVariants();
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                message: this.$tc('sw-privileges.tooltip.warning'),
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role),
            };
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
            this.productEntity.media.forEach(({ id, mediaId, position, media }) => {
                const mediaItem = this.productMediaRepository.create(Context.api);
                Object.assign(mediaItem, { mediaId, position, productId: this.productEntity.id, media });

                if (this.productEntity.coverId === id) {
                    variant.coverId = mediaItem.id;
                }

                variant.media.push(mediaItem);
            });
        },
    },
});
