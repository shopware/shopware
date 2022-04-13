import template from './sw-settings-tag-list.html.twig';
import './sw-settings-tag-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-tag-list', {
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

        tagCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);

            if (this.sortBy === 'connections' && this.assignmentFilter) {
                criteria.addSorting(Criteria.sort(this.assignmentFilter, this.sortDirection));
            } else {
                criteria.addSorting(Criteria.sort('name', this.sortDirection));
            }

            Object.entries(this.tagDefinition.properties).forEach(([propertyName, property]) => {
                if (property.relation === 'many_to_many') {
                    criteria.addAggregation(
                        Criteria.terms(
                            propertyName,
                            'id',
                            null,
                            null,
                            Criteria.count(propertyName, `tag.${propertyName}.id`),
                        ),
                    );
                }
            });

            if (this.assignmentFilter && !this.emptyFilter) {
                criteria.addFilter(Criteria.not('AND', [
                    Criteria.equals(`tag.${this.assignmentFilter}.id`, null),
                ]));
            }

            return criteria;
        },

        tagColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: 'sw-settings-tag.list.columnName',
                routerLink: 'sw.settings.tag.detail',
                width: '200px',
                primary: true,
                allowResize: true,
            }, {
                property: 'connections',
                label: 'sw-settings-tag.list.columnConnections',
                allowResize: false,
                sortable: !!this.assignmentFilter,
            }];
        },

        assignmentFilterOptions() {
            const options = [];

            Object.entries(this.tagDefinition.properties).forEach(([propertyName, property]) => {
                if (property.relation === 'many_to_many') {
                    options.push({
                        value: propertyName,
                        label: this.$tc(`sw-settings-tag.list.connections.${propertyName}`, 0),
                    });
                }
            });

            return options;
        },

        filterCount() {
            let count = 0;

            if (this.assignmentFilter || this.emptyFilter) {
                count += 1;
            }

            if (this.duplicateFilter) {
                count += 1;
            }

            return count;
        },
    },

    methods: {
        getList() {
            this.isLoading = true;

            if (this.sortBy === 'connections' && !this.assignmentFilter) {
                this.sortBy = 'name';
            }

            if (this.$refs.swCardFilter && this.$refs.swCardFilter.term !== this.term) {
                this.$refs.swCardFilter.term = this.term ?? '';
            }

            if (this.duplicateFilter || this.emptyFilter || this.sortBy === 'connections') {
                this.tagApiService.filterIds(this.tagCriteria.parse(), {
                    duplicateFilter: this.duplicateFilter,
                    emptyFilter: this.emptyFilter,
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

        getConnections(id) {
            const counts = this.getCounts(id);
            const connections = [];

            Object.keys(counts).forEach((property) => {
                if (this.assignmentFilter && this.assignmentFilter !== property) {
                    return;
                }

                connections.push({
                    property,
                    entity: this.tagDefinition.properties[property].entity,
                    count: counts[property],
                });
            });

            return connections;
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
});
