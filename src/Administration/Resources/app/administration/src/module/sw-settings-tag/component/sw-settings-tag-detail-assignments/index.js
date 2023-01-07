import utils from 'src/core/service/util.service';
import template from './sw-settings-tag-detail-assignments.html.twig';
import './sw-settings-tag-detail-assignments.scss';

const { Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,
    inheritAttrs: false,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('listing'),
    ],

    props: {
        tag: {
            type: Object,
            required: true,
        },
        toBeAdded: {
            type: Object,
            required: true,
        },
        toBeDeleted: {
            type: Object,
            required: true,
        },
        initialCounts: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },
        property: {
            type: String,
            required: false,
            default: null,
        },
        entity: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            selectedEntity: this.entity ?? 'product',
            selectedAssignment: this.property ?? 'products',
            entitiesGridKey: null,
            preSelected: {},
            entities: null,
            isLoading: false,
            showSelected: this.property && this.entity,
            counts: { ...this.initialCounts },
            currentPageCountBuckets: [],
            disableRouteParams: true,
            page: 1,
            limit: 25,
        };
    },

    computed: {
        tagDefinition() {
            return Shopware.EntityDefinition.get('tag');
        },

        isInheritable() {
            return Shopware.EntityDefinition.get(this.selectedEntity)?.properties?.tags?.flags?.inherited === true;
        },

        assignmentAssociations() {
            const assignmentAssociations = [];

            Object.entries(this.tagDefinition.properties).forEach(([propertyName, property]) => {
                if (property.relation === 'many_to_many') {
                    assignmentAssociations.push({
                        name: this.$tc(`sw-settings-tag.detail.assignments.${propertyName}`),
                        entity: property.entity,
                        assignment: propertyName,
                    });
                }
            });

            return assignmentAssociations;
        },

        assignmentAssociationsColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                primary: true,
                allowResize: false,
                sortable: false,
            }];
        },

        entityRepository() {
            return this.repositoryFactory.create(this.selectedEntity);
        },

        entityCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            if (this.selectedEntity === 'product') {
                criteria.addAssociation('options.group');
            }
            if (this.selectedEntity === 'order') {
                criteria.addAssociation('orderCustomer');
            }

            if (this.isInheritable) {
                this.addTagAggregations(criteria);
            }

            if (!this.showSelected) {
                return criteria;
            }

            const toBeAdded = Object.keys(this.toBeAdded[this.selectedAssignment]);
            const toBeDeleted = Object.keys(this.toBeDeleted[this.selectedAssignment]).filter((id) => {
                const parentId = this.toBeDeleted[this.selectedAssignment][id].parentId;

                if (!this.isInheritable || !parentId) {
                    return true;
                }

                return !this.isInherited(id, parentId) || !this.hasInheritedTag(id, parentId);
            });

            if (toBeAdded.length) {
                criteria.addFilter(Criteria.multi('OR', [
                    Criteria.equals('tags.id', this.tag.id),
                    Criteria.equalsAny('id', toBeAdded),
                ]));
            } else {
                criteria.addFilter(Criteria.equals('tags.id', this.tag.id));
            }

            if (!toBeDeleted.length) {
                return criteria;
            }

            criteria.addFilter(Criteria.not('AND', [
                Criteria.equalsAny('id', toBeDeleted),
            ]));

            return criteria;
        },

        entitiesColumns() {
            return [{
                property: 'name',
                primary: true,
                allowResize: false,
                sortable: false,
            }];
        },

        selectedAssignments() {
            const selection = new Proxy(({ ...this.preSelected }), {
                get(target, key) {
                    return target[key];
                },
                set(target, key, value) {
                    target[key] = value;
                    return true;
                },
            });

            Object.values(this.toBeAdded[this.selectedAssignment]).forEach((toBeAdded) => {
                this.$set(selection, toBeAdded.id, toBeAdded);
            });

            Object.values(this.toBeDeleted[this.selectedAssignment]).forEach((toBeDeleted) => {
                if (selection.hasOwnProperty(toBeDeleted.id)) {
                    this.$delete(selection, toBeDeleted.id);
                }
            });

            return selection;
        },

        totalAssignments() {
            let total = 0;

            Object.values(this.counts).forEach((count) => {
                total += count;
            });

            return total;
        },
    },

    watch: {
        selectedEntity() {
            this.page = 1;
            this.getList();
        },

        showSelected() {
            this.page = 1;
            this.getList();
        },
    },

    methods: {
        getList() {
            this.isLoading = true;
            const criteria = this.entityCriteria;

            if (this.showSelected && this.isInheritable) {
                return this.searchInheritedEntities(criteria).then(() => {
                    return this.search(criteria);
                }).catch(() => {
                    this.isLoading = false;
                });
            }

            return this.search(criteria);
        },

        search(criteria) {
            return this.entityRepository.search(criteria, {
                ...Context.api,
                inheritance: true,
            }).then((items) => {
                if (this.tag.isNew() || items.total === 0) {
                    this.entitiesGridKey = utils.createId();
                    this.total = items.total;
                    this.entities = items;
                    this.isLoading = false;

                    return null;
                }

                const entityIds = items.map(({ id }) => {
                    return id;
                });
                const relationCriteria = new Criteria(1, this.limit);
                relationCriteria.addFilter(Criteria.equalsAny('id', entityIds));
                if (this.isInheritable) {
                    this.addTagAggregations(relationCriteria, false);
                }
                relationCriteria.addPostFilter(Criteria.equals('tags.id', this.tag.id));

                return this.entityRepository.search(relationCriteria).then((selected) => {
                    if (this.isInheritable) {
                        this.currentPageCountBuckets = selected.aggregations.tags.buckets;
                    }

                    const preSelected = {};
                    selected.forEach((item) => {
                        preSelected[item.id] = item;
                    });
                    this.preSelected = preSelected;
                    this.entitiesGridKey = utils.createId();

                    this.total = items.total;
                    this.entities = items;
                    this.isLoading = false;
                });
            }).catch(() => {
                this.isLoading = false;
            });
        },

        addTagAggregations(criteria, filter = true) {
            let aggregation = Criteria.count('tags', `${this.selectedEntity}.tags.id`);

            if (filter) {
                aggregation = Criteria.filter(
                    'tags',
                    [Criteria.equals('tags.id', this.tag.id)],
                    aggregation,
                );

                criteria.addAggregation(
                    Criteria.terms(
                        'parentTags',
                        'id',
                        null,
                        null,
                        Criteria.count('parentTags', `${this.selectedEntity}.parent.tags.id`),
                    ),
                );
            }

            criteria.addAggregation(
                Criteria.terms(
                    'tags',
                    'id',
                    null,
                    null,
                    aggregation,
                ),
            );
        },

        searchInheritedEntities(criteria) {
            const toBeAdded = Object.keys(this.toBeAdded[this.selectedAssignment]);
            const toBeDeleted = Object.keys(this.toBeDeleted[this.selectedAssignment]);

            if (!toBeAdded.length && !toBeDeleted.length) {
                return Promise.resolve();
            }

            let addedPromise = Promise.resolve();
            let deletedPromise = Promise.resolve();

            if (toBeAdded.length) {
                const inheritedAddedCriteria = new Criteria(1, 25);
                inheritedAddedCriteria.addFilter(Criteria.multi('AND', [
                    Criteria.equals('tags.id', null),
                    Criteria.equalsAny('parentId', toBeAdded),
                ]));

                addedPromise = this.entityRepository.searchIds(inheritedAddedCriteria).then(({ data, total }) => {
                    if (total === 0) {
                        return;
                    }

                    criteria.filters = [
                        Criteria.multi('OR', [
                            Criteria.multi('AND', criteria.filters),
                            Criteria.equalsAny('id', data),
                        ]),
                    ];
                });
            }

            if (toBeDeleted.length) {
                const inheritedDeletedCriteria = new Criteria(1, 25);
                inheritedDeletedCriteria.addFilter(Criteria.equals('tags.id', null));
                inheritedDeletedCriteria.addFilter(Criteria.equalsAny('parentId', toBeDeleted));
                if (toBeAdded.length) {
                    inheritedDeletedCriteria.addFilter(Criteria.not('AND', [
                        Criteria.equalsAny('id', toBeAdded),
                    ]));
                }

                deletedPromise = this.entityRepository.searchIds(inheritedDeletedCriteria).then(({ data, total }) => {
                    if (total === 0) {
                        return;
                    }

                    criteria.addFilter(Criteria.not('AND', [
                        Criteria.equalsAny('id', data),
                    ]));
                });
            }

            return Promise.all([addedPromise, deletedPromise]);
        },

        async onTermChange(term) {
            this.term = term;
            this.page = 1;
            await this.getList();
        },

        onAssignmentChange({ entity, assignment }) {
            this.selectedEntity = entity;
            this.selectedAssignment = assignment;
        },

        onSelectionChange(selection, item, selected) {
            const id = item.id;

            if (!selected) {
                this.$emit('remove-assignment', this.selectedAssignment, id, item);
                this.countDecrease(this.selectedAssignment);

                return;
            }

            this.$emit('add-assignment', this.selectedAssignment, id, item);
            this.countIncrease(this.selectedAssignment);
        },

        getCount(propertyName) {
            if (this.counts.hasOwnProperty(propertyName)) {
                return this.counts[propertyName];
            }

            return null;
        },

        countIncrease(propertyName) {
            if (this.counts.hasOwnProperty(propertyName)) {
                this.counts[propertyName] += 1;
            } else {
                this.$set(this.counts, propertyName, 1);
            }
        },

        countDecrease(propertyName) {
            if (this.counts.hasOwnProperty(propertyName) && this.counts[propertyName] !== 0) {
                this.counts[propertyName] -= 1;
            } else {
                this.$set(this.counts, propertyName, 0);
            }

            if (!this.showSelected) {
                return;
            }

            if (this.page > 1 && this.entities.length === 1) {
                this.page -= 1;
            }

            this.getList();
        },

        isInherited(id, parentId) {
            if (!this.isInheritable || !parentId || this.toBeAdded[this.selectedAssignment].hasOwnProperty(id)) {
                return false;
            }

            const selfToBeDeleted = this.toBeDeleted[this.selectedAssignment].hasOwnProperty(id);
            const hasOwnTags = this.currentPageCountBuckets.filter(({ key, tags }) => {
                return key === id && (selfToBeDeleted ? tags.count - 1 : tags.count) > 0;
            }).length > 0;

            if (hasOwnTags) {
                return false;
            }

            return this.parentHasTags(id, parentId);
        },

        parentHasTags(id, parentId) {
            const parentToBeDeleted = this.toBeDeleted[this.selectedAssignment].hasOwnProperty(parentId);
            const parentHasTags = this.entities.aggregations.parentTags.buckets.filter(({ key, parentTags }) => {
                return key === id && (parentToBeDeleted ? parentTags.count - 1 : parentTags.count) > 0;
            }).length > 0;

            if (!parentHasTags) {
                return this.toBeAdded[this.selectedAssignment].hasOwnProperty(parentId);
            }

            return true;
        },

        hasInheritedTag(id, parentId) {
            const parentToBeAdded = this.toBeAdded[this.selectedAssignment].hasOwnProperty(parentId);
            const parentToBeDeleted = this.toBeDeleted[this.selectedAssignment].hasOwnProperty(parentId);

            if (this.preSelected.hasOwnProperty(id) || this.toBeDeleted[this.selectedAssignment].hasOwnProperty(id)) {
                return parentToBeAdded || (this.preSelected.hasOwnProperty(parentId) && !parentToBeDeleted);
            }

            const hasInheritedTag = this.entities.aggregations.tags.buckets.filter((bucket) => {
                return bucket.key === id;
            }).length > 0;

            return (hasInheritedTag || parentToBeAdded) && !parentToBeDeleted;
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;

            this.getList();
        },
    },
};
