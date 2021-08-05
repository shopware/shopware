import template from './sw-settings-delivery-time-list.html.twig';
import './sw-settings-delivery-time-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-delivery-time-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder'),
    ],

    data() {
        return {
            deliveryTimes: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        deliveryTimeRepository() {
            return this.repositoryFactory.create('delivery_time');
        },
    },

    methods: {
        getList() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            this.isLoading = true;

            this.deliveryTimeRepository.search(criteria)
                .then((deliveryTime) => {
                    this.total = deliveryTime.total;
                    this.deliveryTimes = deliveryTime;
                    this.isLoading = false;

                    return deliveryTime;
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-delivery-time.list.errorLoad'),
                    });

                    this.isLoading = false;
                    return exception;
                });
        },

        onChangeLanguage() {
            this.getList();
        },

        deliveryTimeColumns() {
            return [{
                property: 'name',
                label: 'sw-settings-delivery-time.list.columnName',
                primary: true,
                routerLink: 'sw.settings.delivery.time.detail',
            }, {
                property: 'unit',
                label: 'sw-settings-delivery-time.list.columnUnit',
            }, {
                property: 'min',
                label: 'sw-settings-delivery-time.list.columnMin',
            }, {
                property: 'max',
                label: 'sw-settings-delivery-time.list.columnMax',
            }];
        },
    },
});
