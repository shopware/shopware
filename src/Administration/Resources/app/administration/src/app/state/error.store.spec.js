import instance from './error.store';

// helper for testing action with expected mutations
const testAction = (action, payload, state, expectedMutations, done) => {
    let count = 0;

    // mock commit
    const commit = (commitType, commitPayload) => {
        const mutation = expectedMutations[count];

        try {
            expect(commitType).toEqual(mutation.type);
            expect(commitPayload).toEqual(mutation.payload);
        } catch (error) {
            done(error);
        }

        count += 1;
        if (count >= expectedMutations.length) {
            done();
        }
    };

    // call the action with mocked store and arguments
    action({ commit, state }, payload);

    // check if no mutations should have been dispatched
    if (expectedMutations.length === 0) {
        expect(count).toEqual(0);
        done();
    }
};

describe('Test actions at file src/app/state/error.store.js', () => {
    let actions = {};

    beforeAll(() => {
        actions = instance.actions;
    });

    it('addApiError', (done) => {
        testAction(actions.addApiError, {
            expression: 'dummy expression',
            error: 'dummy error'
        }, {}, [
            {
                type: 'addApiError',
                payload: {
                    expression: 'dummy expression',
                    error: 'dummy error'
                },
            },
        ], done);
    });

    it('resetApiErrors', (done) => {
        testAction(actions.resetApiErrors, null, {}, [
            { type: 'resetApiErrors' }
        ], done);
    });

    it('removeApiError', (done) => {
        testAction(actions.removeApiError, { expression: 'dummy expression' }, {}, [
            {
                type: 'removeApiError',
                payload: {
                    expression: 'dummy expression',
                },
            },
        ], done);
    });

    it('removeSystemError', (done) => {
        testAction(actions.removeSystemError, { id: 'dummy id' }, {}, [
            { type: 'removeSystemError', payload: { id: 'dummy id' } },
        ], done);
    });

    it('addSystemError', (done) => {
        // mock commit
        const commit = (commitType, commitPayload) => {
            try {
                expect(commitType).toEqual('addSystemError');
                expect(commitPayload).toEqual(
                    { error: { dummyKey: 'dummy error' }, id: 'dummy id' }
                );
            } catch (error) {
                done(error);
            }

            done();
        };

        // call the action with mocked store and arguments
        const id = actions.addSystemError({ commit }, { error: { dummyKey: 'dummy error' }, id: 'dummy id' });

        expect(id).toEqual('dummy id');

        done();
    });
});

describe('Test mutations at file src/app/state/error.store.js', () => {
    let mutations = {};

    let state = {
        api: {},
        system: {},
    };

    beforeAll(() => {
        mutations = instance.mutations;
    });

    beforeEach(() => {
        state = {
            api: {},
            system: {},
        };
    });

    it('removeApiError', () => {
        mutations.removeApiError(state, { expression: 'dummy.expression' });

        expect(state.api).toEqual({});
    });

    it('addApiError', () => {
        mutations.addApiError(state, { expression: 'dummy.expression', error: {} });

        expect(state.api).toEqual({ dummy: { expression: { selfLink: 'dummy.expression' } } });
    });

    it('resetApiErrors', () => {
        mutations.resetApiErrors(state);

        expect(state.api).toEqual({});
    });

    it('addSystemError', () => {
        mutations.addSystemError(state, { id: 'dummy id', error: { code: 'dummy code' } });

        expect(state.system).toEqual({ 'dummy id': { code: 'dummy code' } });
    });

    it('removeSystemError', () => {
        mutations.removeSystemError(state, { id: 'dummy id' });

        expect(state.system).toEqual({});
    });
});

describe('Test getters at file src/app/state/error.store.js', () => {
    let getters = {};

    let state = {};

    beforeAll(() => {
        getters = instance.getters;

        state = {
            api: {
                dummyEntityName: {
                    dummyId: {
                        dummyField: {
                            selfLink: 'dummy.expression',
                        },
                        0: {
                            age: {
                                selfLink: 'dummy.expression',
                            },
                        }
                    },
                },
                dummySystemConfig: {
                    dummySaleChannelId: {
                        dummyKey: {
                            error: 'dummy error',
                        },
                    },
                },
            },
            system: {
                'dummy id': {},
            },
        };
    });

    it('getErrorsForEntity', () => {
        const result = getters.getErrorsForEntity(state)('dummyEntityName', 'dummyId');

        const expected = {
            dummyField: { selfLink: 'dummy.expression' },
            0: { age: { selfLink: 'dummy.expression' } },
        };
        expect(result).toEqual(expected);
    });

    it('getErrorsForEntity empty entity name', () => {
        const result = getters.getErrorsForEntity(state)('not exists entity name', 'dummyId');

        expect(result).toBeNull();
    });

    it('getApiErrorFromPath', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return {};
            }
        }, 'getErrorsForEntity');
        spy.mockReturnValue({ 0: { age: { selfLink: 'dummy.expression' } } });

        const result = getters.getApiErrorFromPath(state, { getErrorsForEntity: spy })('dummyEntityName', 'dummyId', [
            '0',
            'age',
        ]);

        expect(result).toEqual({ selfLink: 'dummy.expression' });
    });

    it('getApiErrorFromPath with empty', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return {};
            }
        }, 'getErrorsForEntity');
        spy.mockReturnValue({ 0: { age: { selfLink: 'dummy.expression' } } });

        const result = getters.getApiErrorFromPath(state, { getErrorsForEntity: spy })('dummyEntityName', 'dummyId', [
            'empty field',
            'empty field 2',
        ]);

        expect(result).toBeNull();
    });

    it('getApiError', () => {
        const entity = {
            getEntityName: () => 'dummyEntityName',
            id: 'dummyId'
        };
        const field = '0.age';

        const spy = jest.spyOn({
            getApiErrorFromPath: () => {
                return {};
            },
        }, 'getApiErrorFromPath');
        spy.mockReturnValue({ selfLink: 'dummy.expression' });

        const result = getters.getApiError(state, { getApiErrorFromPath: spy })(entity, field);

        expect(result).toEqual({ selfLink: 'dummy.expression' });
    });

    it('getSystemConfigApiError', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return {
                    dummyKey: { error: 'dummy error' }
                };
            },
        }, 'getErrorsForEntity');

        const result = getters.getSystemConfigApiError(state, { getErrorsForEntity: spy })('dummySystemConfig', 'dummySaleChannelId', 'dummyKey');

        expect(result).toEqual({ error: 'dummy error' });
    });

    it('getSystemConfigApiError with null entity name', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return {};
            },
        }, 'getErrorsForEntity');

        const result = getters.getSystemConfigApiError(state, { getErrorsForEntity: spy })('empty entity name', 'dummySaleChannelId', 'dummyKey');

        expect(result).toBeNull();
    });

    it('getSystemConfigApiError with null key', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return {
                    dummyKey: { error: 'dummy error' }
                };
            },
        }, 'getErrorsForEntity');

        const result = getters.getSystemConfigApiError(state, { getErrorsForEntity: spy })('dummySystemConfig', 'dummySaleChannelId', 'empty key');

        expect(result).toBeNull();
    });

    it('getSystemConfigApiError with null entity name and sale channel ud', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return null;
            },
        }, 'getErrorsForEntity');

        const result = getters.getSystemConfigApiError(state, { getErrorsForEntity: spy })('dummySystemConfig', 'dummySaleChannelId', 'dummyKey');

        expect(result).toBeNull();
    });

    it('getAllApiErrors', () => {
        const result = getters.getAllApiErrors(state)();

        const expected = [
            { dummyId: { 0: { age: { selfLink: 'dummy.expression', }, }, dummyField: { selfLink: 'dummy.expression', } } },
            { dummySaleChannelId: { dummyKey: { error: 'dummy error' } } }
        ];

        expect(result).toEqual(expected);
    });

    it('getSystemError', () => {
        const result = getters.getSystemError(state)('dummy id');

        expect(result).toEqual({});
    });

    it('existsErrorInProperty', () => {
        const result = getters.existsErrorInProperty(state)('dummyEntityName', '0/age');

        expect(result).toBeTruthy();
    });

    it('existsErrorInProperty with empty entity', () => {
        const result = getters.existsErrorInProperty(state)('empty entity', 'dummyId');

        expect(result).toBeFalsy();
    });

    it('countSystemError', () => {
        const result = getters.countSystemError(state)();

        expect(result).toEqual(1);
    });
});
