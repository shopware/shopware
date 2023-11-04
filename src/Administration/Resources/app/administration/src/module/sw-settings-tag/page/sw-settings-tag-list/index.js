import template from './sw-settings-tag-list.html.twig';
import './sw-settings-tag-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'tagApiService'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            tags: null,
            sortBy: 'name',
            isLoading: false,
            sortDirection: 'ASC',
            showDeleteModal: false,
            showDuplicateModal: false,
            showBulkMergeModal: false,
            duplicateName: null,
            showDetailModal: false,
            detailProperty: null,
            detailEntity: null,
            assignmentFilter: null,
            emptyFilter: false,
            duplicateFilter: false,
            bulkMergeProgress: {
                isRunning: false,
                currentAssignment: null,
                progress: 0,
                total: 0,
            },
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        tagRepository() {
            return this.repositoryFactory.create('tag');
        },

        tagDefinition() {
            return Shopware.EntityDefinition.get('tag');
        },

        assignmentProperties() {
            const properties = [];

            Object.entries(this.tagDefinition.properties).forEach(([propertyName, property]) => {
                if (property.relation !== 'many_to_many') {
                    return;
                }

                properties.push(propertyName);
            });

            return properties;
        },

        tagCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);

            this.setAggregations(criteria);

            const naturalSort = this.sortBy === 'createdAt';
            const sorting = Criteria.sort(this.sortBy, this.sortDirection, naturalSort);

            if (this.assignmentProperties.includes(this.sortBy)) {
                sorting.field += '.id';
                sorting.type = 'count';
            }
            criteria.addSorting(sorting);

            return criteria;
        },

        tagColumns() {
            const columns = [{
                property: 'name',
                dataIndex: 'name',
                label: 'sw-settings-tag.list.columnName',
                routerLink: 'sw.settings.tag.detail',
                width: '200px',
                primary: true,
                allowResize: true,
            }];

            this.assignmentProperties.forEach((propertyName) => {
                columns.push({
                    property: `${propertyName}`,
                    label: this.$tc(`sw-settings-tag.list.assignments.header.${propertyName}`),
                    width: '250px',
                    allowResize: true,
                    sortable: true,
                });
            });

            return columns;
        },

        assignmentFilterOptions() {
            const options = [];

            Object.entries(this.tagDefinition.properties).forEach(([propertyName, property]) => {
                if (property.relation !== 'many_to_many') {
                    return;
                }

                options.push({
                    value: propertyName,
                    label: this.$tc(`sw-settings-tag.list.assignments.filter.${propertyName}`),
                });
            });
            options.sort((a, b) => {
                if (a.label > b.label) { return 1; }
                if (b.label > a.label) { return -1; }
                return 0;
            });

            return options;
        },

        hasAssignmentFilter() {
            return this.assignmentFilter && this.assignmentFilter.length > 0;
        },

        filterCount() {
            let count = 0;

            if (this.hasAssignmentFilter || this.emptyFilter) {
                count += 1;
            }

            if (this.duplicateFilter) {
                count += 1;
            }

            return count;
        },
    },

    methods: {
        setAggregations(criteria) {
            Object.entries(this.tagDefinition.properties).forEach(([propertyName, property]) => {
                if (property.relation !== 'many_to_many') {
                    return;
                }

                criteria.addAggregation(
                    Criteria.terms(
                        propertyName,
                        'id',
                        null,
                        null,
                        Criteria.count(propertyName, `tag.${propertyName}.id`),
                    ),
                );
            });
        },

        getList() {
            this.isLoading = true;

            if (this.$refs.swCardFilter && this.$refs.swCardFilter.term !== this.term) {
                this.$refs.swCardFilter.term = this.term ?? '';
            }

            if (this.duplicateFilter || this.emptyFilter || this.hasAssignmentFilter) {
                this.tagApiService.filterIds(this.tagCriteria.parse(), {
                    duplicateFilter: this.duplicateFilter,
                    emptyFilter: this.emptyFilter,
                    assignmentFilter: this.assignmentFilter,
                }).then(({ total, ids }) => {
                    this.total = total;

                    if (total === 0) {
                        this.tags = null;
                        this.isLoading = false;

                        return;
                    }

                    const criteria = new Criteria(1, this.limit);
                    criteria.setIds(ids);
                    criteria.setTotalCountMode(0);
                    criteria.aggregations = this.tagCriteria.aggregations;
                    criteria.associations = this.tagCriteria.associations;

                    this.tagRepository.search(criteria).then((items) => {
                        items.total = total;
                        this.tags = this.sortByIdsOrder(items, ids);
                        this.isLoading = false;

                        return items;
                    }).catch(() => {
                        this.isLoading = false;
                    });
                }).catch(() => {
                    this.isLoading = false;
                });

                return;
            }

            this.tagRepository.search(this.tagCriteria).then((items) => {
                this.total = items.total;
                this.tags = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        sortByIdsOrder(items, ids) {
            items.sort((a, b) => {
                if (ids.indexOf(a.id) > ids.indexOf(b.id)) {
                    return 1;
                }

                return -1;
            });

            return items;
        },

        getCounts(id) {
            const counts = {};

            Object.entries(this.tagDefinition.properties).forEach(([propertyName, property]) => {
                if (property.relation === 'many_to_many') {
                    const countBucket = this.tags.aggregations[propertyName].buckets.filter((bucket) => {
                        return bucket.key === id;
                    })[0];

                    if (!countBucket[propertyName] || !countBucket[propertyName].count) {
                        return;
                    }

                    counts[propertyName] = countBucket[propertyName].count;
                }
            });

            return counts;
        },

        getPropertyCounting(propertyName, id) {
            if (!this.tags.aggregations[propertyName]) {
                return 0;
            }

            const countBucket = this.tags.aggregations[propertyName].buckets.filter((bucket) => {
                return bucket.key === id;
            })[0];

            if (!countBucket || !countBucket[propertyName] || !countBucket[propertyName].count) {
                return 0;
            }

            return countBucket[propertyName].count;
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;
            this.$nextTick().then(() => {
                this.isLoading = true;
            });

            return this.tagRepository.delete(id).then(() => {
                this.getList();
            });
        },

        onDuplicate(item) {
            this.showDuplicateModal = item.id;
            this.duplicateName = `${item.name} ${this.$tc('global.default.copy')}`;
        },

        onCloseDuplicateModal() {
            this.showDuplicateModal = false;
            this.duplicateName = null;
        },

        onConfirmDuplicate(id) {
            this.showDuplicateModal = false;
            this.$nextTick().then(() => {
                this.isLoading = true;
            });

            const behavior = {
                cloneChildren: false,
                overwrites: {
                    name: this.duplicateName,
                },
            };

            return this.tagRepository.clone(id, Shopware.Context.api, behavior).then(() => {
                this.duplicateName = null;
                this.getList();
            }).catch(() => {
                this.isLoading = false;
                this.duplicateName = null;

                this.createNotificationError({
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            });
        },

        onDetail(id, property, entity) {
            this.showDetailModal = id ?? true;

            if (property && entity) {
                this.detailProperty = property;
                this.detailEntity = entity;
            }
        },

        onCloseDetailModal() {
            this.showDetailModal = false;
            this.detailProperty = null;
            this.detailEntity = null;
        },

        onCloseBulkMergeModal() {
            this.bulkMergeProgress.isRunning = false;
            this.showBulkMergeModal = false;
            this.duplicateName = null;
        },

        onMergeTags(selection) {
            return this.tagApiService.merge(
                Object.keys(selection),
                this.duplicateName,
                this.tagDefinition.properties,
                this.bulkMergeProgress,
            )
                .then(() => {
                    this.duplicateName = null;
                    this.$refs.swSettingsTagGrid.resetSelection();

                    this.bulkMergeProgress.isRunning = false;
                    this.showBulkMergeModal = false;
                    this.$nextTick().then(() => {
                        this.isLoading = true;
                    });

                    this.onFilter();
                })
                .catch(() => {
                    this.bulkMergeProgress.isRunning = false;
                    this.createNotificationError({
                        message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                    });
                });
        },

        getBulkMergeMessageGlue(ids, id) {
            if (ids.length - 1 === ids.indexOf(id)) {
                return this.bulkMergeProgress.isRunning
                    ? this.$tc('sw-settings-tag.list.bulkMergeInto')
                    : this.$tc('sw-settings-tag.list.bulkMergeMessageFinal');
            }

            if (ids.length - 2 === ids.indexOf(id)) {
                return this.$tc('sw-settings-tag.list.bulkMergeMessageAnd');
            }

            return ',';
        },

        onSaveFinish() {
            this.onCloseDetailModal();

            this.$nextTick().then(() => {
                this.getList();
            });
        },

        onFilter() {
            if (this.assignmentFilter && this.emptyFilter) {
                this.assignmentFilter = null;
            }

            this.page = 1;
            this.getList();
        },

        resetFilters() {
            this.assignmentFilter = null;
            this.emptyFilter = false;
            this.duplicateFilter = false;

            this.onFilter();
        },
    },
};
