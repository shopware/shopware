import template from './sw-order-nested-line-items-modal.html.twig';
import './sw-order-nested-line-items-modal.scss';

const { Component, Filter } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-nested-line-items-modal', {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        lineItem: {
            type: Object,
            required: true,
        },

        order: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
        lineItemRepository() {
            return this.repositoryFactory.create('order_line_item');
        },

        modalTitle() {
            const price = Filter.getByName('currency')(this.lineItem.totalPrice, this.order.currency.shortName);

            return this.$tc('sw-order.nestedLineItemsModal.titlePrefix', 0, {
                lineItemLabel: this.lineItem.label,
                price,
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            if (this.lineItem === null) {
                return;
            }
            this.isLoading = true;
            this.lineItem.nestingLevel = 0;

            await this.enrichNestedLineItems(this.lineItem.children);
        },

        /**
         * Fetches all children by their parentIds and also adds the children of that result via association.
         * Therefore, each recursive loop iteration will add 2 additional nesting levels at once with one request.
         *
         * @param nestedLineItems - A (children) lineItem collection, to be enriched with all descendants
         * @return {Promise<void>}
         */
        async enrichNestedLineItems(nestedLineItems, nestingLevel = 1) {
            if (nestedLineItems === null || nestedLineItems.length <= 0) {
                this.isLoading = false;

                return;
            }

            const parentIds = nestedLineItems.map(lineItem => lineItem.id);
            const criteria = (new Criteria())
                .addFilter(Criteria.equalsAny('parentId', parentIds));

            criteria
                .getAssociation('children')
                .addSorting(Criteria.naturalSorting('label'));

            const children = await this.lineItemRepository.search(criteria, Shopware.Context.api);

            const descendants = [];
            nestedLineItems.forEach((nestedLineItem) => {
                nestedLineItem.nestingLevel = nestingLevel;
                nestedLineItem.children = children.filter(child => child.parentId === nestedLineItem.id);
                nestedLineItem.children.sort(this.naturalSort);

                descendants.push(...nestedLineItem.children);
            });

            await this.enrichNestedLineItems(descendants, nestingLevel + 1);
        },

        naturalSort(a, b) {
            return a.label.localeCompare(
                b.label,
                'en-GB',
                {
                    numeric: true,
                    ignorePunctuation: true,
                },
            );
        },

        onCloseModal() {
            this.$emit('modal-close');
        },
    },
});
