import assignmentModalComponent from './index';

type Assignment = {
    id: string;
    salesChannelId: string | null;
    contentLayoutId: string;
    [field: string]: unknown;
};

function createCollection(assignments: Assignment[]): Assignment[] & { add: (assignment: Assignment) => void } {
    return Object.assign([...assignments], {
        add(this: Assignment[], assignment: Assignment): void {
            this.push(assignment);
        },
    });
}

describe('module/sw-experience-studio/component/sw-experience-studio-assignment-modal', () => {
    const methods = (assignmentModalComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> })
        .methods;
    const computed = (assignmentModalComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> })
        .computed;

    const categoryAssignmentType = {
        entity: 'category',
        assignmentEntity: 'category_content_layout',
        entityIdField: 'categoryId',
        defaultConfigKey: 'core.content_system.default_category_content_layout',
    };

    it('resolves the assignment type from the layout root source', () => {
        expect(computed.assignmentType.call({ rootSource: 'product' })).toEqual({
            entity: 'product',
            assignmentEntity: 'product_content_layout',
            entityIdField: 'productId',
            defaultConfigKey: 'core.content_system.default_product_content_layout',
        });
        expect(computed.assignmentType.call({ rootSource: 'landing_page' })).toBeNull();
    });

    it('limits product selection to parent products', () => {
        const criteria = computed.entityCriteria.call({ assignmentType: { entity: 'product' } }) as {
            filters: unknown[];
        };

        expect(criteria.filters).toEqual([{ type: 'equals', field: 'parentId', value: null }]);
    });

    it('excludes link categories from selection', () => {
        const criteria = computed.entityCriteria.call({ assignmentType: { entity: 'category' } }) as {
            filters: unknown[];
        };

        expect(criteria.filters).toEqual([
            {
                type: 'not',
                operator: 'AND',
                queries: [{ type: 'equals', field: 'type', value: 'link' }],
            },
        ]);
    });

    it('loads the global assignments of the layout and whether it is the default', async () => {
        const search = jest.fn().mockResolvedValue(
            createCollection([
                { id: 'assignment-1', categoryId: 'category-1', salesChannelId: null, contentLayoutId: 'layout-1' },
            ]),
        );
        const vm = {
            layoutId: 'layout-1',
            assignmentType: categoryAssignmentType,
            assignmentRepository: { search },
            acl: { can: () => true },
            systemConfigApiService: {
                getValues: jest.fn().mockResolvedValue({
                    'core.content_system.default_category_content_layout': 'layout-1',
                }),
            },
            isLoading: false,
            entityIds: [] as string[],
            assignmentIdsByEntityId: {},
            isDefault: false,
            wasDefault: false,
        };

        await methods.loadAssignments.call(vm);

        const [[criteria]] = search.mock.calls as [[{ filters: unknown[] }]];
        expect(criteria.filters).toEqual([
            { type: 'equals', field: 'contentLayoutId', value: 'layout-1' },
            { type: 'equals', field: 'salesChannelId', value: null },
        ]);
        expect(vm.assignmentIdsByEntityId).toEqual({ 'category-1': 'assignment-1' });
        expect(vm.entityIds).toEqual(['category-1']);
        expect(vm.isDefault).toBe(true);
        expect(vm.wasDefault).toBe(true);
        expect(vm.isLoading).toBe(false);
    });

    it('notifies and closes when loading the assignments fails', async () => {
        const emit = jest.fn();
        const createNotificationError = jest.fn();
        const vm = {
            layoutId: 'layout-1',
            assignmentType: categoryAssignmentType,
            assignmentRepository: { search: jest.fn().mockRejectedValue(new Error('failed')) },
            acl: { can: () => true },
            systemConfigApiService: { getValues: jest.fn().mockResolvedValue({}) },
            isLoading: false,
            createNotificationError,
            $t: (key: string) => key,
            $emit: emit,
        };

        await methods.loadAssignments.call(vm);

        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.detail.assignmentModal.messageLoadError',
        });
        expect(emit).toHaveBeenCalledWith('close');
        expect(vm.isLoading).toBe(false);
    });

    it('deletes the assignments of deselected entities', async () => {
        const syncDeleted = jest.fn().mockResolvedValue(undefined);
        const vm = {
            layoutId: 'layout-1',
            assignmentType: categoryAssignmentType,
            assignmentRepository: { syncDeleted },
            assignmentIdsByEntityId: { 'category-1': 'assignment-1', 'category-2': 'assignment-2' },
            entityIds: ['category-2'],
        };

        await methods.saveEntityAssignments.call(vm);

        expect(syncDeleted).toHaveBeenCalledWith(['assignment-1'], expect.anything());
    });

    it('creates assignments for newly selected entities and moves global assignments of other layouts', async () => {
        const otherLayoutAssignment = {
            id: 'assignment-other',
            categoryId: 'category-moved',
            salesChannelId: null,
            contentLayoutId: 'layout-other',
        };
        const assignments = createCollection([otherLayoutAssignment]);
        const saveAll = jest.fn().mockResolvedValue(undefined);
        const search = jest.fn().mockResolvedValue(assignments);
        const vm = {
            layoutId: 'layout-1',
            assignmentType: categoryAssignmentType,
            assignmentRepository: {
                search,
                saveAll,
                syncDeleted: jest.fn(),
                create: jest.fn(() => ({ id: 'assignment-new' })),
            },
            assignmentIdsByEntityId: {},
            entityIds: [
                'category-moved',
                'category-new',
            ],
        };

        await methods.saveEntityAssignments.call(vm);

        const [[criteria]] = search.mock.calls as [[{ filters: unknown[] }]];
        expect(criteria.filters).toEqual([
            { type: 'equalsAny', field: 'categoryId', value: 'category-moved|category-new' },
            { type: 'equals', field: 'salesChannelId', value: null },
        ]);
        expect(vm.assignmentRepository.syncDeleted).not.toHaveBeenCalled();
        expect(saveAll).toHaveBeenCalledWith(assignments, expect.anything());
        expect([...assignments]).toEqual([
            { ...otherLayoutAssignment, contentLayoutId: 'layout-1' },
            { id: 'assignment-new', categoryId: 'category-new', salesChannelId: null, contentLayoutId: 'layout-1' },
        ]);
    });

    it('sets the layout as the default of its type', async () => {
        const saveValues = jest.fn().mockResolvedValue(undefined);
        const vm = {
            layoutId: 'layout-1',
            assignmentType: categoryAssignmentType,
            acl: { can: () => true },
            systemConfigApiService: { saveValues },
            isDefault: true,
            wasDefault: false,
        };

        await methods.saveDefault.call(vm);

        expect(saveValues).toHaveBeenCalledWith({
            'core.content_system.default_category_content_layout': 'layout-1',
        });
    });

    it('unsets the default when the layout is no longer the default of its type', async () => {
        const saveValues = jest.fn().mockResolvedValue(undefined);
        const vm = {
            layoutId: 'layout-1',
            assignmentType: categoryAssignmentType,
            acl: { can: () => true },
            systemConfigApiService: { saveValues },
            isDefault: false,
            wasDefault: true,
        };

        await methods.saveDefault.call(vm);

        expect(saveValues).toHaveBeenCalledWith({
            'core.content_system.default_category_content_layout': null,
        });
    });

    it('leaves the default untouched when it did not change', async () => {
        const saveValues = jest.fn();
        const vm = {
            layoutId: 'layout-1',
            assignmentType: categoryAssignmentType,
            acl: { can: () => true },
            systemConfigApiService: { saveValues },
            isDefault: true,
            wasDefault: true,
        };

        await methods.saveDefault.call(vm);

        expect(saveValues).not.toHaveBeenCalled();
    });

    it('skips the default without the system config permission', async () => {
        const getValues = jest.fn();
        const saveValues = jest.fn();
        const loadVm = {
            layoutId: 'layout-1',
            assignmentType: categoryAssignmentType,
            assignmentRepository: { search: jest.fn().mockResolvedValue(createCollection([])) },
            acl: { can: () => false },
            systemConfigApiService: { getValues, saveValues },
            isLoading: false,
            entityIds: [] as string[],
            assignmentIdsByEntityId: {},
            isDefault: false,
            wasDefault: false,
        };

        await methods.loadAssignments.call(loadVm);
        await methods.saveDefault.call({ ...loadVm, isDefault: true });

        expect(getValues).not.toHaveBeenCalled();
        expect(saveValues).not.toHaveBeenCalled();
        expect(loadVm.isDefault).toBe(false);
    });

    it('does not save without edit permission', async () => {
        const saveEntityAssignments = jest.fn();
        const vm = {
            allowEdit: false,
            saveEntityAssignments,
        };

        await methods.onSave.call(vm);

        expect(saveEntityAssignments).not.toHaveBeenCalled();
    });

    it('notifies, reloads the persisted assignments and keeps the modal open when saving fails', async () => {
        const emit = jest.fn();
        const createNotificationError = jest.fn();
        const vm = {
            allowEdit: true,
            isLoading: false,
            loadAssignments: jest.fn().mockResolvedValue(undefined),
            saveEntityAssignments: jest.fn().mockRejectedValue(new Error('failed')),
            saveDefault: jest.fn(),
            createNotificationSuccess: jest.fn(),
            createNotificationError,
            $t: (key: string) => key,
            $emit: emit,
        };

        await methods.onSave.call(vm);

        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.detail.assignmentModal.messageSaveError',
        });
        expect(vm.loadAssignments).toHaveBeenCalled();
        expect(emit).not.toHaveBeenCalled();
        expect(vm.isLoading).toBe(false);
    });

    it('closes after saving assignments and default', async () => {
        const emit = jest.fn();
        const vm = {
            allowEdit: true,
            isLoading: false,
            saveEntityAssignments: jest.fn().mockResolvedValue(undefined),
            saveDefault: jest.fn().mockResolvedValue(undefined),
            createNotificationSuccess: jest.fn(),
            createNotificationError: jest.fn(),
            $t: (key: string) => key,
            $emit: emit,
        };

        await methods.onSave.call(vm);

        expect(vm.saveEntityAssignments).toHaveBeenCalled();
        expect(vm.saveDefault).toHaveBeenCalled();
        expect(emit).toHaveBeenCalledWith('close');
    });
});
