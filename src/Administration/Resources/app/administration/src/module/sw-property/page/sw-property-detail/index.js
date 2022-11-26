/*
 * @package inventory
 */

import template from './sw-property-detail.html.twig';
import './sw-property-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('product.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    props: {
        groupId: {
            type: String,
            default: null,
        },
    },

    data() {
        return {
            propertyGroup: null,
            isLoading: false,
            isSaveSuccessful: false,
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.propertyGroup, 'name');
        },

        optionRepository() {
            return this.repositoryFactory.create(
                this.propertyGroup.options.entity,
                this.propertyGroup.options.source,
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
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
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
        },

        showCustomFields() {
            return this.propertyGroup && this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        groupId() {
            this.loadEntityData();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-property-group-detail__propertyGroup',
                path: 'propertyGroup',
                scope: this,
            });
            this.loadEntityData();
            this.loadCustomFieldSets();
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

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('property_group').then((sets) => {
                this.customFieldSets = sets;
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

            return this.propertyRepository.save(this.propertyGroup).then(() => {
                this.loadEntityData();
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((exception) => {
                this.createNotificationError({
                    message: this.$tc('sw-property.detail.messageSaveError'),
                });
                this.isLoading = false;
                throw exception;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.property.index' });
        },
    },
};
