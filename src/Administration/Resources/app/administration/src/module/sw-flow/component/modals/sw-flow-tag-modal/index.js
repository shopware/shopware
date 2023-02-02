import template from './sw-flow-tag-modal.html.twig';

const { Component, Mixin, Context } = Shopware;
const { ShopwareError } = Shopware.Classes;
const { EntityCollection, Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();

/**
 * @private
 * @package business-ops
 */
export default {
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
        action: {
            type: String,
            required: false,
            default: null,
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
            const criteria = new Criteria(1, 25);
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

        tagTitle() {
            if (!this.action) return '';

            if (this.action.match(/add.*tag/)) {
                return this.$tc('sw-flow.modals.tag.labelAddTag');
            }

            if (this.action.match(/remove.*tag/)) {
                return this.$tc('sw-flow.modals.tag.labelRemoveTag');
            }

            return '';
        },

        ...mapState('swFlowState', ['triggerEvent', 'triggerActions']),
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

            if (config?.entity) {
                this.entity = config?.entity;
            }

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
            if (!this.triggerEvent) {
                this.entityOptions = [];
                return;
            }

            const allowedAware = this.triggerEvent.aware ?? [];
            // eslint-disable-next-line max-len
            const options = this.flowBuilderService.getAvailableEntities(this.action, this.triggerActions, allowedAware, ['tags']);

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
    },
};
