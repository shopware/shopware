import template from './sw-flow-tag-modal.html.twig';

const { Component, Mixin, Context, EntityDefinition } = Shopware;
const { ShopwareError } = Shopware.Classes;
const { EntityCollection, Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();
const { capitalizeString, snakeCase } = Shopware.Utils.string;

Component.register('sw-flow-tag-modal', {
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'flowBuilderService',
    ],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            entity: null,
            entityOptions: [],
            tagCollection: null,
            tagError: null,
            entityError: null,
        };
    },

    computed: {
        tagCriteria() {
            const criteria = new Criteria();
            const { config } = this.sequence;
            const tagIds = Object.keys(config.tagIds);
            if (tagIds.length) {
                criteria.addFilter(Criteria.equalsAny('id', tagIds));
            }

            return criteria;
        },

        isNewTag() {
            return !this.sequence?.id;
        },

        tagRepository() {
            return this.repositoryFactory.create('tag');
        },

        ...mapState('swFlowState', ['triggerEvent']),
    },

    watch: {
        entity(value) {
            if (value && this.entityError) {
                this.entityError = null;
            }
        },

        tagCollection(value) {
            if (value && this.tagError) {
                this.tagError = null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getEntityOptions();
            this.tagCollection = this.createTagCollection();

            const { config, id } = this.sequence;
            if (id && config?.tagIds) {
                this.getTagCollection();
            }
        },

        getTagCollection() {
            return this.tagRepository.search(this.tagCriteria)
                .then(tags => {
                    this.tagCollection = tags;
                })
                .catch(() => {
                    this.tagCollection = [];
                });
        },

        createTagCollection() {
            return new EntityCollection(
                this.tagRepository.route,
                this.tagRepository.entityName,
                Context.api,
            );
        },

        onAddTag(data) {
            this.tagCollection.add(data);
        },

        onRemoveTag(data) {
            this.tagCollection.remove(data);
        },

        getEntityOptions() {
            const options = [];
            if (!this.triggerEvent) {
                this.entityOptions = [];
                return;
            }

            Object.entries(this.triggerEvent.data).forEach(([key, value]) => {
                if (value.type !== 'entity') {
                    return;
                }

                const hasTagsAssociation = EntityDefinition.get(snakeCase(key)).properties?.tags;
                if (!hasTagsAssociation) {
                    return;
                }

                options.push({
                    label: this.convertEntityName(key),
                    value: key,
                });
            });

            if (options.length) {
                this.entity = options[0].value;
            }

            this.entityOptions = options;
        },

        getConfig() {
            const tagIds = {};
            this.tagCollection.forEach(tag => {
                Object.assign(tagIds, {
                    [tag.id]: tag.name,
                });
            });

            const config = {
                entity: this.entity,
                tagIds,
            };
            return config;
        },

        fieldError(field) {
            if (!field || !field.length) {
                return new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });
            }

            return null;
        },

        onSaveTag() {
            this.tagError = this.fieldError(this.tagCollection);
            this.entityError = this.fieldError(this.entity);
            if (this.tagError || this.entityError) {
                return;
            }

            const config = this.getConfig();
            const data = {
                ...this.sequence,
                config,
            };
            this.$emit('process-finish', data);
            this.onClose();
        },

        onClose() {
            this.tagError = null;
            this.$emit('modal-close');
        },

        convertEntityName(camelCaseText) {
            if (!camelCaseText) return '';

            const normalText = camelCaseText.replace(/([A-Z])/g, ' $1');
            return capitalizeString(normalText);
        },
    },
});
