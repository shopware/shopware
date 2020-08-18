import template from './sw-property-detail.html.twig';
import './sw-property-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-property-detail', {
    template,

    inject: [
        'repositoryFactory',
        'acl'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('product.editor');
            },
            method: 'onSave'
        },
        ESCAPE: 'onCancel'
    },

    props: {
        groupId: {
            type: String
        }
    },

    watch: {
        groupId() {
            this.loadEntityData();
        }
    },

    data() {
        return {
            propertyGroup: null,
            isLoading: false,
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.propertyGroup, 'name');
        },

        optionRepository() {
            return this.repositoryFactory.create(
                this.propertyGroup.options.entity,
                this.propertyGroup.options.source
            );
        },

        propertyRepository() {
            return this.repositoryFactory.create('property_group');
        },

        tooltipSave() {
            if (!this.acl.can('property.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('property.editor'),
                    showOnDisabledElements: true
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        },

        defaultCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('options');
            criteria.setTerm(this.term);

            return criteria;
        },

        useNaturalSorting() {
            return this.sortBy === 'property.name';
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadEntityData();
        },

        loadEntityData() {
            this.isLoading = true;

            this.propertyRepository.get(this.groupId, Shopware.Context.api, this.defaultCriteria)
                .then((currentGroup) => {
                    this.propertyGroup = currentGroup;
                    this.isLoading = false;
                }).catch(() => {
                    this.isLoading = false;
                });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        abortOnLanguageChange() {
            return this.propertyRepository.hasChanges(this.propertyGroup);
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.propertyRepository.save(this.propertyGroup, Shopware.Context.api).then(() => {
                this.loadEntityData();
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((exception) => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-property.detail.messageSaveError')
                });
                this.isLoading = false;
                throw exception;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.property.index' });
        }
    }
});
