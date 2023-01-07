import template from './sw-settings-tag-detail-modal.html.twig';
import './sw-settings-tag-detail-modal.scss';

const { Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'syncService',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    props: {
        editedTag: {
            type: Object,
            required: false,
            default: null,
        },
        counts: {
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
            tag: null,
            isLoading: false,
            assignmentsToBeAdded: {},
            assignmentsToBeDeleted: {},
            initialTab: this.property && this.entity ? 'assignments' : 'general',
        };
    },

    computed: {
        tagRepository() {
            return this.repositoryFactory.create('tag');
        },

        tagDefinition() {
            return Shopware.EntityDefinition.get('tag');
        },

        ...mapPropertyErrors('tag', ['name']),

        title() {
            return this.tag.isNew()
                ? this.$tc('sw-settings-tag.list.buttonAddTag')
                : this.$tc('sw-settings-tag.detail.editTitle', 0, { name: this.tag.name });
        },

        allowSave() {
            return this.tag.isNew()
                ? this.acl.can('tag.creator')
                : this.acl.can('tag.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        computedCounts() {
            const counts = { ...this.counts };

            Object.keys(this.assignmentsToBeDeleted).forEach((propertyName) => {
                if (!counts.hasOwnProperty(propertyName)) {
                    return;
                }

                counts[propertyName] -= Object.keys(this.assignmentsToBeDeleted[propertyName]).length;
            });

            Object.keys(this.assignmentsToBeAdded).forEach((propertyName) => {
                if (!counts.hasOwnProperty(propertyName)) {
                    counts[propertyName] = Object.keys(this.assignmentsToBeAdded[propertyName]).length;

                    return;
                }

                counts[propertyName] += Object.keys(this.assignmentsToBeAdded[propertyName]).length;
            });

            return counts;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.editedTag) {
                this.tag = Object.assign(this.tagRepository.create(), this.editedTag);
                this.tag._isNew = false;
            } else {
                this.tag = this.tagRepository.create();
            }

            Object.entries(this.tagDefinition.properties).forEach(([propertyName, property]) => {
                if (property.relation === 'many_to_many') {
                    this.$set(this.assignmentsToBeAdded, propertyName, {});
                    this.$set(this.assignmentsToBeDeleted, propertyName, {});
                }
            });
        },

        async onSave() {
            this.isLoading = true;
            const deletePayload = [];

            Object.entries(this.tagDefinition.properties).forEach(([propertyName, property]) => {
                if (property.relation !== 'many_to_many') {
                    return;
                }

                const toBeAdded = Object.keys(this.assignmentsToBeAdded[propertyName]);

                if (toBeAdded.length !== 0) {
                    toBeAdded.forEach((id) => {
                        this.tag[propertyName].add(this.assignmentsToBeAdded[propertyName][id]);
                    });
                }

                const toBeDeleted = Object.keys(this.assignmentsToBeDeleted[propertyName]);

                if (toBeDeleted.length === 0) {
                    return;
                }

                const ids = toBeDeleted.map((id) => {
                    return {
                        [property.reference]: id,
                        [property.local]: this.tag.id,
                    };
                });

                deletePayload.push({
                    action: 'delete',
                    entity: property.mapping,
                    payload: ids,
                });
            });

            if (deletePayload.length) {
                await this.syncService.sync(deletePayload, {}, { 'single-operation': 1 });
            }

            return this.tagRepository.save(this.tag).then(() => {
                this.$emit('finish');
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
                this.isLoading = false;
            });
        },

        onCancel() {
            this.$emit('close');
        },

        addAssignment(assignment, id, item) {
            if (this.assignmentsToBeDeleted[assignment].hasOwnProperty(id)) {
                this.$delete(this.assignmentsToBeDeleted[assignment], id);

                return;
            }

            this.$set(this.assignmentsToBeAdded[assignment], id, item);
        },

        removeAssignment(assignment, id, item) {
            if (this.assignmentsToBeAdded[assignment].hasOwnProperty(id)) {
                this.$delete(this.assignmentsToBeAdded[assignment], id);

                return;
            }

            this.$set(this.assignmentsToBeDeleted[assignment], id, item);
        },
    },
};
