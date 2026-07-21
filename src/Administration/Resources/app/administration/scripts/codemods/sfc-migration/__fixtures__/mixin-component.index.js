import template from './mixin-component.html.twig';
import listingMixin from 'src/app/mixin/listing.mixin';

Shopware.Component.register('sw-mixin-list', {
    template,

    mixins: [
        Shopware.Mixin.getByName('notification'),
        listingMixin,
    ],

    data() {
        return {
            items: [],
            isLoading: false,
        };
    },

    computed: {
        total() {
            return this.items.length;
        },
    },

    methods: {
        async loadItems() {
            this.isLoading = true;
            try {
                this.items = await this.fetchItems();
            } finally {
                this.isLoading = false;
            }
        },

        onNotify() {
            this.createNotificationSuccess({ message: 'Done' });
        },
    },
});
