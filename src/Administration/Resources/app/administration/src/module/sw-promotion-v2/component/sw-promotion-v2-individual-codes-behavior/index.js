import template from './sw-promotion-v2-individual-codes-behavior.html.twig';
import './sw-promotion-v2-individual-codes-behavior.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-promotion-v2-individual-codes-behavior', {
    template,

    inject: [
        'acl',
        'repositoryFactory'
    ],

    mixins: [
        'notification'
    ],

    props: {
        promotion: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            isGridLoading: false,
            codeDeleteModal: false,
            codeBulkDeleteModal: false,
            generateCodesModal: false,
            currentSelection: []
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        deleteConfirmText() {
            return this.$tc(
                'sw-promotion-v2.detail.base.codes.individual.textDeleteConfirm',
                this.currentSelection.length,
                { code: this.currentSelection[0].code || '' }
            );
        },

        codeColumns() {
            return [{
                property: 'code',
                label: this.$tc('sw-promotion-v2.detail.base.codes.individual.columnCode')
            }, {
                property: 'payload',
                label: this.$tc('sw-promotion-v2.detail.base.codes.individual.columnRedeemed')
            }, {
                property: 'payload.customerName',
                label: this.$tc('sw-promotion-v2.detail.base.codes.individual.columnCustomer')
            }];
        }
    },

    methods: {
        onSearchTermChange(term) {
            this.isGridLoading = true;
            this.promotion.individualCodes.criteria.setTerm(term);

            this.loadIndividualCodesGrid();
        },

        loadIndividualCodesGrid() {
            this.promotion.individualCodes.criteria.setPage(1);
            this.promotion.individualCodes.criteria.addSorting(Criteria.naturalSorting('code'));

            this.$refs.individualCodesGrid.load().then(() => {
                this.isGridLoading = false;
            });
        },

        onSelectionChange() {
            this.currentSelection = Object.values(this.$refs.individualCodesGrid.selection);
        },

        onShowCodeDeleteModal(id) {
            this.codeDeleteModal = id;
        },

        onShowCodeBulkDeleteModal() {
            this.codeBulkDeleteModal = true;
        },

        onConfirmCodeDelete(id) {
            this.onCloseDeleteModal();
            this.$refs.individualCodesGrid.deleteItem(id).then(() => {
                this.loadIndividualCodesGrid();
            });
        },

        onConfirmCodeBulkDelete() {
            this.onCloseBulkDeleteModal();
            this.$refs.individualCodesGrid.deleteItems().then(() => {
                this.loadIndividualCodesGrid();
            });
        },

        onCloseDeleteModal() {
            this.codeDeleteModal = false;
        },

        onCloseBulkDeleteModal() {
            this.codeBulkDeleteModal = false;
        },

        onOpenGenerateCodesModal() {
            this.generateCodesModal = true;
        },

        onCloseGenerateCodesModal() {
            this.generateCodesModal = false;
        },

        routeToCustomer(redeemedCustomer) {
            return this.customerRepository.get(redeemedCustomer.customerId, Shopware.Context.api).then((result) => {
                if (result === null) {
                    this.createRoutingErrorNotification(redeemedCustomer.customerName);
                    return;
                }

                this.$router.push({
                    name: 'sw.customer.detail',
                    params: { id: result.id }
                });
            }).catch(() => {
                this.createRoutingErrorNotification(redeemedCustomer.customerName);
            });
        },

        createRoutingErrorNotification(name) {
            this.createNotificationError({
                message: this.$tc('sw-promotion-v2.detail.base.codes.individual.routingError', 0, { name })
            });
        }
    }
});
