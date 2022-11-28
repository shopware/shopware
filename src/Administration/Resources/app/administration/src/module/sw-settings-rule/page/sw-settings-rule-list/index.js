import './sw-settings-rule-list.scss';
import template from './sw-settings-rule-list.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'filterFactory',
        'ruleConditionDataProviderService',
        'filterService',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            rules: null,
            isLoading: false,
            sortBy: 'name',
            storeKey: 'grid.filter.rule',
            activeFilterNumber: 0,
            defaultFilters: [
                'conditions',
                'conditionGroups',
                'assignments',
                'tags',
            ],
            filterCriteria: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        getRuleDefinition() {
            return Shopware.EntityDefinition.get('rule');
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        conditionFilterOptions() {
            const conditions = this.ruleConditionDataProviderService.getConditions().map((condition) => {
                return { value: condition.type, label: this.$tc(condition.label) };
            });
            conditions.sort((a, b) => a.label.localeCompare(b.label));

            return conditions;
        },

        groupFilterOptions() {
            const groupFilter = [];
            Object.values(this.ruleConditionDataProviderService.getGroups()).forEach((group) => {
                const conditionFilterString = this.ruleConditionDataProviderService.getByGroup(group.id).map((condition) => {
                    return condition.type;
                }).join('|');

                groupFilter.push({
                    value: conditionFilterString,
                    label: this.$tc(group.name),
                });
            });
            groupFilter.sort((a, b) => a.label.localeCompare(b.label));

            return groupFilter;
        },

        associationFilterOptions() {
            const associations = [];
            this.assignmentProperties.forEach((propertyName) => {
                associations.push({
                    value: propertyName,
                    label: this.$tc(`sw-settings-rule.filter.assignmentFilter.values.${propertyName}`),
                });
            });
            associations.sort((a, b) => a.label.localeCompare(b.label));

            return associations;
        },

        listFilters() {
            const filters = {
                conditionGroups: {
                    property: 'conditions.type',
                    label: this.$tc('sw-settings-rule.filter.groupFilter.label'),
                    placeholder: this.$tc('sw-settings-rule.filter.groupFilter.placeholder'),
                    type: 'multi-select-filter',
                    options: this.groupFilterOptions,
                },
                conditions: {
                    property: 'conditions.type',
                    label: this.$tc('sw-settings-rule.filter.conditionFilter.label'),
                    placeholder: this.$tc('sw-settings-rule.filter.conditionFilter.placeholder'),
                    type: 'multi-select-filter',
                    options: this.conditionFilterOptions,
                },
                assignments: {
                    existingType: true,
                    property: 'conditions',
                    label: this.$tc('sw-settings-rule.filter.assignmentFilter.label'),
                    placeholder: this.$tc('sw-settings-rule.filter.assignmentFilter.placeholder'),
                    type: 'multi-select-filter',
                    options: this.associationFilterOptions,
                },
                tags: {
                    property: 'tags',
                    label: this.$tc('sw-settings-rule.filter.tagFilter.label'),
                    placeholder: this.$tc('sw-settings-rule.filter.tagFilter.placeholder'),
                    criteria: (new Criteria(1, 25)).addSorting(Criteria.sort('name')),
                },
            };

            return this.filterFactory.create('rule', filters);
        },

        listCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            const naturalSort = ['createdAt', 'updatedAt'].includes(this.sortBy);
            const sorting = Criteria.sort(this.sortBy, this.sortDirection, naturalSort);

            if (this.assignmentProperties.includes(this.sortBy)) {
                sorting.field += '.id';
                sorting.type = 'count';
            }
            criteria.addSorting(sorting);

            criteria.addAssociation('tags');

            this.setAggregations(criteria);

            this.filterCriteria.forEach(filter => {
                criteria.addFilter(filter);
            });

            return criteria;
        },

        assignmentProperties() {
            const properties = [];

            Object.keys(this.getRuleDefinition.properties).forEach((propertyName) => {
                if (propertyName === 'conditions' || propertyName === 'tags') {
                    return;
                }

                const property = this.getRuleDefinition.properties[propertyName];
                if (property.relation === 'many_to_many' || property.relation === 'one_to_many') {
                    properties.push(propertyName);
                }
            });

            return properties;
        },
    },

    methods: {
        setAggregations(criteria) {
            Object.keys(this.getRuleDefinition.properties).forEach((propertyName) => {
                if (propertyName === 'conditions' || propertyName === 'tags') {
                    return;
                }

                const property = this.getRuleDefinition.properties[propertyName];

                if (property.relation === 'many_to_many' || property.relation === 'one_to_many') {
                    criteria.addAggregation(
                        Criteria.terms(
                            propertyName,
                            'id',
                            null,
                            null,
                            Criteria.count(propertyName, `rule.${propertyName}.id`),
                        ),
                    );
                }
            });
        },

        getCounts(propertyName, id) {
            const countBucket = this.rules.aggregations[propertyName].buckets.filter((bucket) => {
                return bucket.key === id;
            })[0];

            if (!countBucket[propertyName] || !countBucket[propertyName].count) {
                return 0;
            }

            return countBucket[propertyName].count;
        },

        async getList() {
            this.isLoading = true;

            const criteria = await this.filterService.mergeWithStoredFilters(this.storeKey, this.listCriteria);

            this.activeFilterNumber = criteria.filters.length;

            this.ruleRepository.search(criteria).then((items) => {
                this.total = items.total;
                this.rules = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.getList();
        },

        onDuplicate(referenceRule) {
            const behaviour = {
                overwrites: {
                    name: `${referenceRule.name} ${this.$tc('global.default.copy')}`,
                    // setting the createdAt to null, so that api does set a new date
                    createdAt: null,
                },
            };

            this.ruleRepository.clone(referenceRule.id, Shopware.Context.api, behaviour).then((duplicatedData) => {
                this.$router.push(
                    {
                        name: 'sw.settings.rule.detail',
                        params: { id: duplicatedData.id },
                    },
                );
            });
        },

        onInlineEditSave(promise, rule) {
            this.isLoading = true;

            promise.then(() => {
                this.isLoading = false;

                this.createNotificationSuccess({
                    message: this.$tc('sw-settings-rule.detail.messageSaveSuccess', 0, { name: rule.name }),
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    message: this.$tc('sw-settings-rule.detail.messageSaveError'),
                });
            });
        },

        updateCriteria(criteria) {
            this.page = 1;
            this.filterCriteria = criteria;
            return this.getList();
        },

        getRuleColumns() {
            const columns = [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: 'sw-settings-rule.list.columnName',
                routerLink: 'sw.settings.rule.detail',
                width: '250px',
                allowResize: true,
                primary: true,
            }, {
                property: 'priority',
                label: 'sw-settings-rule.list.columnPriority',
                inlineEdit: 'number',
                allowResize: true,
            }, {
                property: 'description',
                label: 'sw-settings-rule.list.columnDescription',
                width: '250px',
                allowResize: true,
            }, {
                property: 'updatedAt',
                label: 'sw-settings-rule.list.columnDateUpdated',
                align: 'right',
                allowResize: true,
            }, {
                property: 'createdAt',
                label: 'sw-settings-rule.list.columnDateCreated',
                align: 'right',
                allowResize: true,
            }, {
                property: 'invalid',
                label: 'sw-settings-rule.list.columnStatus',
                allowResize: true,
            }, {
                property: 'tags',
                label: 'sw-settings-rule.list.columnTags',
                width: '250px',
                allowResize: true,
                sortable: false,
                visible: false,
            }];

            this.assignmentProperties.forEach((propertyName) => {
                const labelPostfix = propertyName.charAt(0).toUpperCase() + propertyName.slice(1);
                columns.push({
                    property: `${propertyName}`,
                    label: `sw-settings-rule.list.column${labelPostfix}`,
                    width: '250px',
                    allowResize: true,
                    sortable: true,
                    visible: false,
                });
            });

            return columns;
        },
    },
};
