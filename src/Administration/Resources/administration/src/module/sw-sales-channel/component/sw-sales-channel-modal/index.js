import template from './sw-sales-channel-modal.html.twig';
import './sw-sales-channel-modal.scss';

const { Component } = Shopware;

Component.register('sw-sales-channel-modal', {
    template,

    data() {
        return {
            detailType: null
        };
    },

    computed: {
        modalTitle() {
            if (this.detailType) {
                return this.$tc('sw-sales-channel.modal.titleDetailPrefix', 0, { name: this.detailType.name });
            }

            return this.$tc('sw-sales-channel.modal.title');
        }
    },

    methods: {
        onGridOpenDetails(detailType) {
            this.detailType = detailType;
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        onAddChannel(id) {
            this.onCloseModal();

            if (id) {
                this.$router.push({ name: 'sw.sales.channel.create', params: { typeId: id } });
            }
        }
    }
});
