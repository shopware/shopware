import template from './sw-sales-channel-defaults-select.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-sales-channel-defaults-select', {
    template,

    inject: ['feature'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        salesChannel: {
            type: Object,
            required: false,
            default: null,
        },

        propertyName: {
            type: String,
            required: true,
        },

        propertyLabel: {
            type: String,
            required: true,
        },

        defaultPropertyName: {
            type: String,
            required: true,
        },

        defaultPropertyLabel: {
            type: String,
            required: true,
        },

        propertyNameInDomain: {
            type: String,
            required: false,
            default: null,

        },

        helpText: {
            type: String,
            required: false,
            default: null,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        criteria: {
            type: Object,
            required: false,
            default: undefined,
        },
    },

    computed: {
        propertyCollection: {
            get() {
                if (!this.salesChannel) {
                    return [];
                }

                return this.salesChannel[this.propertyName];
            },
            set(newCollection) {
                if (!this.salesChannel) {
                    return;
                }
                this.salesChannel[this.propertyName] = newCollection;
            },
        },

        defaultId: {
            get() {
                if (!this.salesChannel) {
                    return null;
                }

                return this.salesChannel[this.defaultPropertyName];
            },
            set(newDefaultId) {
                if (this.salesChannel) {
                    this.salesChannel[this.defaultPropertyName] = newDefaultId;
                }
            },
        },

        propertyEntityName() {
            return this.propertyCollection ? this.propertyCollection.entity : null;
        },

        propertyNameKebabCase() {
            return Shopware.Utils.string.kebabCase(this.propertyName);
        },

        multiSelectClass() {
            return `sw-sales-channel-detail__select-${this.propertyNameKebabCase}`;
        },

        singleSelectClass() {
            return `sw-sales-channel-detail__assign-${this.propertyNameKebabCase}`;
        },

        defaultsValueError() {
            return Shopware.State.getters['error/getApiError'](this.salesChannel, this.defaultPropertyName);
        },

        labelProperty() {
            if (this.propertyEntityName === 'payment_method') {
                return 'distinguishableName';
            }

            return 'name';
        },
    },

    methods: {
        updateCollection(collection) {
            if (collection.length > this.propertyCollection.length) {
                this.addItem(collection);
                return;
            }
            this.removeItem(collection);
        },

        getNotInCollection(collectionWith, collectionWithout) {
            const additionalElement = collectionWith.find((searched) => {
                return !collectionWithout.some((included) => {
                    return included.id === searched.id;
                });
            });

            return additionalElement || null;
        },

        addItem(collection) {
            const added = this.getNotInCollection(collection, this.propertyCollection);
            this.propertyCollection = collection;

            if (this.propertyCollection.length === 1) {
                this.defaultId = added.id;
            }
        },

        removeItem(collection) {
            const removed = this.getNotInCollection(this.propertyCollection, collection);
            if (removed === null) {
                return;
            }

            if (this.propertyNameInDomain) {
                const domain = this.getDomainUsingValue(removed);
                if (domain !== null) {
                    this.createNotificationError({
                        message: this.$tc(
                            'sw-sales-channel.sw-sales-channel-defaults-select.messageError',
                            0,
                            { url: domain.url },
                        ),
                    });
                    return;
                }
            }

            this.propertyCollection = collection;
            if (this.defaultId === removed.id) {
                this.defaultId = null;
            }
        },

        getDomainUsingValue(item) {
            return this.salesChannel.domains.find((domain) => {
                return domain[this.propertyNameInDomain] === item.id;
            }) || null;
        },

        updateDefault(defaultId, defaultEntity) {
            this.defaultId = defaultId;

            if (!!defaultId && !this.propertyCollection.has(defaultId)) {
                this.propertyCollection.add(defaultEntity);
            }
        },
    },
});
