describe('module/sw-flow/store/flow.store', () => {
    const store = Shopware.Store.get('swFlow');

    beforeEach(() => {
        store.$reset();
    });

    it('should initialize with default state', () => {
        expect(store.flow.eventName).toBe('');
        expect(store.flow.sequences).toEqual([]);
        expect(store.originFlow).toEqual({});
        expect(store.triggerEvent).toEqual({});
        expect(store.triggerEvents).toEqual([]);
        expect(store.triggerActions).toEqual([]);
        expect(store.invalidSequences).toEqual([]);
        expect(store.stateMachineState).toEqual([]);
        expect(store.documentTypes).toEqual([]);
        expect(store.mailTemplates).toEqual([]);
        expect(store.customFieldSets).toEqual([]);
        expect(store.customFields).toEqual([]);
        expect(store.customerGroups).toEqual([]);
        expect(store.restrictedRules).toEqual([]);
        expect(store.appActions).toEqual([]);
        expect(store.originAvailableActions).toEqual([]);
    });

    it('should set flow correctly', () => {
        const flow = {
            eventName: 'testEvent',
            sequences: [{ actionName: 'testAction', ruleId: 'testRule', config: {} }] as EntityCollection<'flow_sequence'>,
            description: 'testDescription',
        } as Entity<'flow'>;

        store.setFlow(flow);

        expect(store.flow.eventName).toBe('testEvent');
        expect(store.flow.sequences).toEqual(flow.sequences);
        expect(store.flow.description).toBe('testDescription');
    });

    it('should set flow correctly from config', () => {
        const flow = {
            config: {
                description: 'testDescription',
                sequences: [{ actionName: 'testAction', ruleId: 'testRule', config: {} }],
                eventName: 'testEvent',
            },
        } as { config: Entity<'flow'> };

        store.setFlow(flow as unknown as Entity<'flow'>);

        expect(store.flow.eventName).toBe('testEvent');
        expect(store.flow.sequences).toEqual(flow.config.sequences);
        expect(store.flow.description).toBe('testDescription');
    });

    it('should set origin flow correctly', () => {
        const flow = {
            eventName: 'testEvent',
            sequences: [{ actionName: 'testAction', ruleId: 'testRule', config: {} }],
        } as Entity<'flow'>;

        store.setOriginFlow(flow);

        expect(store.originFlow.eventName).toBe('testEvent');
        expect(store.originFlow.sequences).toEqual(flow.sequences);
    });

    it('should add sequence correctly', () => {
        const sequence = { actionName: 'testAction', ruleId: 'testRule', config: {} } as Entity<'flow_sequence'>;

        store.addSequence(sequence);

        expect(store.flow.sequences).toContainEqual(sequence);
    });

    it('should remove sequences correctly', () => {
        const sequence1 = { id: '1', actionName: 'testAction1', ruleId: 'testRule1', config: {} } as Entity<'flow_sequence'>;
        const sequence2 = { id: '2', actionName: 'testAction2', ruleId: 'testRule2', config: {} } as Entity<'flow_sequence'>;

        store.addSequence(sequence1);
        store.addSequence(sequence2);

        store.removeSequences(['1']);

        expect(store.flow.sequences).not.toContainEqual(sequence1);
        expect(store.flow.sequences).toContainEqual(sequence2);
    });

    it('should reset flow state correctly', () => {
        store.resetFlowState();

        expect(store.flow.eventName).toBe('');
        expect(store.flow.sequences).toEqual([]);
        expect(store.invalidSequences).toEqual([]);
        expect(store.triggerEvent).toEqual({});
    });

    it('should update a sequence correctly', () => {
        const sequence = { id: '1', actionName: 'testAction', ruleId: 'testRule', config: {} } as Entity<'flow_sequence'>;

        store.addSequence(sequence);

        const updatedSequence = {
            id: '1',
            actionName: 'testActionUpdated',
            ruleId: 'testRuleUpdated',
            config: {},
        } as Entity<'flow_sequence'>;

        store.updateSequence(updatedSequence);

        expect(store.flow.sequences).toContainEqual(updatedSequence);
    });

    it('should get the selected app action', () => {
        const appActions = [
            { name: 'appAction1', label: 'App Action 1' },
            { name: 'appAction2', label: 'App Action 2' },
        ] as EntityCollection<'app_flow_action'>;

        store.appActions = appActions;

        const selectedAppAction = store.getSelectedAppAction('appAction2');

        expect(selectedAppAction).toEqual(appActions[1]);
    });
});
