import type CriteriaType from 'src/core/data/criteria.data';
import template from './sw-experience-studio-assignment-modal.html.twig';
import './sw-experience-studio-assignment-modal.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const DEFAULT_LAYOUT_CONFIG_DOMAIN = 'core.content_system';

/**
 * The layout types whose layouts can be assigned from Experience Studio, keyed by the layout root source.
 * The default config keys mirror the Core `CONFIG_KEY_DEFAULT_CONTENT_LAYOUT` constants of the assignment definitions.
 */
type LayoutAssignmentType = {
    entity: 'product' | 'category';
    assignmentEntity: 'product_content_layout' | 'category_content_layout';
    entityIdField: 'productId' | 'categoryId';
    defaultConfigKey: string;
};

const LAYOUT_ASSIGNMENT_TYPES: Record<string, LayoutAssignmentType> = {
    product: {
        entity: 'product',
        assignmentEntity: 'product_content_layout',
        entityIdField: 'productId',
        defaultConfigKey: 'core.content_system.default_product_content_layout',
    },
    category: {
        entity: 'category',
        assignmentEntity: 'category_content_layout',
        entityIdField: 'categoryId',
        defaultConfigKey: 'core.content_system.default_category_content_layout',
    },
};

type AssignmentEntity = {
    id: string;
    salesChannelId: string | null;
    contentLayoutId: string;
    [entityIdField: string]: unknown;
};

type AssignmentCollection = AssignmentEntity[] & {
    add: (entity: AssignmentEntity) => void;
};

type AssignmentRepository = {
    create: (context: apiContext) => AssignmentEntity;
    search: (criteria: CriteriaType, context: apiContext) => Promise<AssignmentCollection>;
    saveAll: (entities: AssignmentCollection, context: apiContext) => Promise<unknown>;
    syncDeleted: (ids: string[], context: apiContext) => Promise<void>;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'systemConfigApiService',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        layoutId: {
            type: String,
            required: true,
        },
        rootSource: {
            type: String,
            required: true,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['close'],

    data(): {
        isLoading: boolean;
        entityIds: string[];
        assignmentIdsByEntityId: Record<string, string>;
        isDefault: boolean;
        wasDefault: boolean;
    } {
        return {
            isLoading: false,
            entityIds: [],
            assignmentIdsByEntityId: {},
            isDefault: false,
            wasDefault: false,
        };
    },

    computed: {
        assignmentType(): LayoutAssignmentType | null {
            return LAYOUT_ASSIGNMENT_TYPES[this.rootSource] ?? null;
        },

        assignmentRepository(): AssignmentRepository | null {
            if (!this.assignmentType) {
                return null;
            }

            return this.repositoryFactory.create(this.assignmentType.assignmentEntity) as unknown as AssignmentRepository;
        },

        entityRepository() {
            return this.assignmentType ? this.repositoryFactory.create(this.assignmentType.entity) : null;
        },

        entityCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);

            if (this.assignmentType?.entity === 'product') {
                criteria.addFilter(Criteria.equals('parentId', null));
            }

            if (this.assignmentType?.entity === 'category') {
                criteria.addFilter(Criteria.not('AND', [Criteria.equals('type', 'link')]));
            }

            return criteria;
        },
    },

    created(): void {
        void this.loadAssignments();
    },

    methods: {
        async loadAssignments(): Promise<void> {
            const assignmentType = this.assignmentType;
            const repository = this.assignmentRepository;

            if (!assignmentType || !repository) {
                return;
            }

            this.isLoading = true;

            try {
                const criteria = new Criteria(1, null);
                criteria.addFilter(Criteria.equals('contentLayoutId', this.layoutId));
                criteria.addFilter(Criteria.equals('salesChannelId', null));
                criteria.setTotalCountMode(0);

                const assignments = await repository.search(criteria, Shopware.Context.api);
                const config = this.acl.can('system.system_config')
                    ? ((await this.systemConfigApiService.getValues(DEFAULT_LAYOUT_CONFIG_DOMAIN)) as Record<
                          string,
                          unknown
                      >)
                    : {};

                this.assignmentIdsByEntityId = Object.fromEntries(
                    assignments.map((assignment) => [
                        assignment[assignmentType.entityIdField] as string,
                        assignment.id,
                    ]),
                );
                this.entityIds = Object.keys(this.assignmentIdsByEntityId);
                this.wasDefault = config[assignmentType.defaultConfigKey] === this.layoutId;
                this.isDefault = this.wasDefault;
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.detail.assignmentModal.messageLoadError'),
                });
                this.$emit('close');
            } finally {
                this.isLoading = false;
            }
        },

        onChangeEntityIds(entityIds: string[] | null): void {
            this.entityIds = entityIds ?? [];
        },

        onChangeDefault(isDefault: boolean): void {
            this.isDefault = isDefault;
        },

        onModalRootChange(isOpen: boolean): void {
            if (!isOpen) {
                this.onCancel();
            }
        },

        onCancel(): void {
            this.$emit('close');
        },

        async onSave(): Promise<void> {
            if (!this.allowEdit) {
                return;
            }

            this.isLoading = true;

            try {
                await this.saveEntityAssignments();
                await this.saveDefault();

                this.createNotificationSuccess({
                    message: this.$t('sw-experience-studio.detail.assignmentModal.messageSaved'),
                });
                this.$emit('close');
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.detail.assignmentModal.messageSaveError'),
                });

                await this.loadAssignments();
            } finally {
                this.isLoading = false;
            }
        },

        async saveEntityAssignments(): Promise<void> {
            const assignmentType = this.assignmentType;
            const repository = this.assignmentRepository;

            if (!assignmentType || !repository) {
                return;
            }

            const removedAssignmentIds = Object.keys(this.assignmentIdsByEntityId)
                .filter((entityId) => !this.entityIds.includes(entityId))
                .map((entityId) => this.assignmentIdsByEntityId[entityId]);
            const addedEntityIds = this.entityIds.filter((entityId) => !this.assignmentIdsByEntityId[entityId]);

            if (removedAssignmentIds.length > 0) {
                await repository.syncDeleted(removedAssignmentIds, Shopware.Context.api);
            }

            if (addedEntityIds.length === 0) {
                return;
            }

            const criteria = new Criteria(1, addedEntityIds.length);
            criteria.addFilter(Criteria.equalsAny(assignmentType.entityIdField, addedEntityIds));
            criteria.addFilter(Criteria.equals('salesChannelId', null));
            criteria.setTotalCountMode(0);

            const assignments = await repository.search(criteria, Shopware.Context.api);
            const reassignedEntityIds = assignments.map((assignment) => assignment[assignmentType.entityIdField]);

            assignments.forEach((assignment) => {
                assignment.contentLayoutId = this.layoutId;
            });

            addedEntityIds
                .filter((entityId) => !reassignedEntityIds.includes(entityId))
                .forEach((entityId) => {
                    const assignment = repository.create(Shopware.Context.api);
                    assignment[assignmentType.entityIdField] = entityId;
                    assignment.salesChannelId = null;
                    assignment.contentLayoutId = this.layoutId;

                    assignments.add(assignment);
                });

            await repository.saveAll(assignments, Shopware.Context.api);
        },

        async saveDefault(): Promise<void> {
            if (!this.assignmentType || !this.acl.can('system.system_config') || this.isDefault === this.wasDefault) {
                return;
            }

            await this.systemConfigApiService.saveValues({
                [this.assignmentType.defaultConfigKey]: this.isDefault ? this.layoutId : null,
            });
        },
    },
});
