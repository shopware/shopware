/*
 * @package inventory
 */

import template from './sw-product-variant-modal.html.twig';
import './sw-product-variant-modal.scss';

const { Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
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
            isLoading: false,
            groups: [],
            filterOptions: [],
            includeOptions: [],
            filterWindowOpen: false,
            showBulkEditModal: false,
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

        groupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        contextMenuEditText() {
            return this.acl.can('product.editor') ?
                this.$tc('global.default.edit') :
                this.$tc('global.default.view');
        },

        filterCriteria() {
            if (this.includeOptions.length <= 0) {
                return [];
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

        productVariantCriteria() {
            const criteria = new Criteria(this.paginationPage, this.paginationLimit);

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

            // User selected filters
            if (this.filterCriteria) {
                this.filterCriteria.forEach((cri) => {
                    criteria.addFilter(cri);
                });
            }

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, true));

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
                    property: 'sales',
                    dataIndex: 'sales',
                    label: this.$tc('sw-product.list.columnSales'),
                    allowResize: true,
                    align: 'right',
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
            const criteria = new Criteria(1, 25);
            const variantIds = this.toBeDeletedVariants.map(variant => variant.id);
            criteria.addFilter(Criteria.equalsAny('canonicalProductId', variantIds));

            return criteria;
        },

        groupCriteria() {
            return new Criteria(1, 100);
        },

        selectedGroups() {
            // get groups for selected options
            const groupIds = this.productEntity?.configuratorSettings.reduce((result, element) => {
                if (result.indexOf(element.option.groupId) < 0) {
                    result.push(element.option.groupId);
                }

                return result;
            }, []);

            return this.groups?.filter((group) => {
                return groupIds.indexOf(group.id) >= 0;
            });
        },

        filterOptionsListing() {
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
            return [...groups, ...children];
        },
    },

    watch: {
        selectedGroups() {
            this.filterOptions = this.filterOptionsListing;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchProductVariants();
            this.fetchSystemCurrency();
            this.loadGroups();
        },

        fetchSystemCurrency() {
            const systemCurrencyId = Shopware.Context.app.systemCurrencyId;

            this.currencyRepository.get(systemCurrencyId).then(response => {
                this.currency = response;
            });
        },

        fetchProductVariants() {
            this.isLoading = true;

            return this.productRepository.search(this.productVariantCriteria)
                .then(response => {
                    this.productVariants = response;
                }).finally(() => {
                    this.isLoading = false;
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

                        this.$refs.variantGrid.resetSelection();

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

        loadGroups() {
            return this.groupRepository.search(this.groupCriteria).then((searchResult) => {
                this.groups = searchResult;
            });
        },

        resetFilterOptions() {
            this.filterOptions = [];
            this.includeOptions = [];

            this.$nextTick(() => {
                this.filterOptions = this.filterOptionsListing;
                this.fetchProductVariants();
            });
        },

        filterOptionChecked(option) {
            if (option.checked) {
                // Remove from include list
                this.includeOptions.push({
                    id: option.id,
                    groupId: option.parentId,
                });
                return;
            }
            // Remove option from option list which is unchecked
            this.includeOptions = this.includeOptions.filter((includeOption) => includeOption.id !== option.id);
        },

        getOptionsForGroup(groupId) {
            return this.productEntity?.configuratorSettings.filter((element) => {
                return !element.isDeleted && element.option.groupId === groupId;
            });
        },

        toggleFilterMenu() {
            this.filterWindowOpen = !this.filterWindowOpen;
        },

        toggleBulkEditModal() {
            this.showBulkEditModal = !this.showBulkEditModal;
        },

        async onEditItems() {
            await this.$nextTick();

            let includesDigital = '0';
            const digital = Object.values(this.$refs.variantGrid.selection)
                .filter(product => product.states.includes('is-download'));
            if (digital.length > 0) {
                includesDigital = (digital.filter(product => product.isCloseout).length !== digital.length) ? '1' : '2';
            }

            this.$router.push({
                name: 'sw.bulk.edit.product',
                params: {
                    parentId: this.productEntity.id,
                    includesDigital,
                },
            });
        },

        variantIsDigital(variant) {
            return variant.states && variant.states.includes('is-download');
        },
    },
};
