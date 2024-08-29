import instance from './error.store';

describe('Test actions at file src/app/state/error.store.js', () => {
    it('addApiError', () => {
        const expectedMutations = [{
            type: 'addApiError',
            payload: {
                expression: 'dummy expression',
                error: 'dummy error',
            },
        }];

        let count = 0;
        const commit = (commitType, commitPayload) => {
            const mutation = expectedMutations[count];

            expect(commitType).toEqual(mutation.type);
            expect(commitPayload).toEqual(mutation.payload);

            count += 1;
        };

        instance.actions.addApiError({ commit }, { expression: 'dummy expression', error: 'dummy error' });

        if (count !== expectedMutations.length) {
            throw new Error('Not as many mutations than expected');
        }
    });

    it('resetApiErrors', () => {
        const expectedMutations = [{
            type: 'resetApiErrors',
            payload: undefined,
        }];

        let count = 0;
        const commit = (commitType, commitPayload) => {
            const mutation = expectedMutations[count];

            expect(commitType).toEqual(mutation.type);
            expect(commitPayload).toEqual(mutation.payload);

            count += 1;
        };

        instance.actions.resetApiErrors({ commit });

        if (count !== expectedMutations.length) {
            throw new Error('Not as many mutations than expected');
        }
    });

    it('removeApiError', () => {
        const expectedMutations = [{
            type: 'removeApiError',
            payload: {
                expression: 'dummy expression',
            },
        }];

        let count = 0;
        const commit = (commitType, commitPayload) => {
            const mutation = expectedMutations[count];

            expect(commitType).toEqual(mutation.type);
            expect(commitPayload).toEqual(mutation.payload);

            count += 1;
        };

        instance.actions.removeApiError({ commit }, { expression: 'dummy expression' });

        if (count !== expectedMutations.length) {
            throw new Error('Not as many mutations than expected');
        }
    });

    it('removeSystemError', () => {
        const expectedMutations = [{
            type: 'removeSystemError',
            payload: {
                id: 'dummy id',
            },
        }];

        let count = 0;
        const commit = (commitType, commitPayload) => {
            const mutation = expectedMutations[count];

            expect(commitType).toEqual(mutation.type);
            expect(commitPayload).toEqual(mutation.payload);

            count += 1;
        };

        instance.actions.removeSystemError({ commit }, { id: 'dummy id' });

        if (count !== expectedMutations.length) {
            throw new Error('Not as many mutations than expected');
        }
    });

    it('addSystemError', () => {
        // mock commit
        const commit = (commitType, commitPayload) => {
            expect(commitType).toBe('addSystemError');
            expect(commitPayload).toEqual({
                error: {
                    dummyKey: 'dummy error',
                },
                id: 'dummy id',
            });
        };

        // call the action with mocked store and arguments
        const id = instance.actions.addSystemError({ commit }, { error: { dummyKey: 'dummy error' }, id: 'dummy id' });

        expect(id).toBe('dummy id');
    });
});

describe('Test mutations at file src/app/state/error.store.js', () => {
    let state = {
        api: {},
        system: {},
    };

    beforeEach(() => {
        state = {
            api: {},
            system: {},
        };
    });

    it('removeApiError', () => {
        instance.mutations.removeApiError(state, { expression: 'dummy.expression' });

        expect(state.api).toEqual({});
    });

    it('addApiError', () => {
        instance.mutations.addApiError(state, { expression: 'dummy.expression', error: {} });

        expect(state.api).toEqual({ dummy: { expression: { selfLink: 'dummy.expression' } } });
    });

    it('resetApiErrors', () => {
        instance.mutations.resetApiErrors(state);

        expect(state.api).toEqual({});
    });

    it('addSystemError', () => {
        instance.mutations.addSystemError(state, { id: 'dummy id', error: { code: 'dummy code' } });

        expect(state.system).toEqual({ 'dummy id': { code: 'dummy code' } });
    });

    it('removeSystemError', () => {
        instance.mutations.removeSystemError(state, { id: 'dummy id' });

        expect(state.system).toEqual({});
    });
});

describe('Test getters at file src/app/state/error.store.js', () => {
    let state = {};

    beforeEach(() => {
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
                        },
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
        const result = instance.getters.getErrorsForEntity(state)('dummyEntityName', 'dummyId');

        const expected = {
            dummyField: { selfLink: 'dummy.expression' },
            0: { age: { selfLink: 'dummy.expression' } },
        };
        expect(result).toEqual(expected);
    });

    it('getErrorsForEntity empty entity name', () => {
        const result = instance.getters.getErrorsForEntity(state)('not exists entity name', 'dummyId');

        expect(result).toBeNull();
    });

    it('getApiErrorFromPath', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return {};
            },
        }, 'getErrorsForEntity');
        spy.mockReturnValue({ 0: { age: { selfLink: 'dummy.expression' } } });

        const result = instance.getters.getApiErrorFromPath(state, { getErrorsForEntity: spy })('dummyEntityName', 'dummyId', [
            '0',
            'age',
        ]);

        expect(result).toEqual({ selfLink: 'dummy.expression' });
    });

    it('getApiErrorFromPath with empty', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return {};
            },
        }, 'getErrorsForEntity');
        spy.mockReturnValue({ 0: { age: { selfLink: 'dummy.expression' } } });

        const result = instance.getters.getApiErrorFromPath(state, { getErrorsForEntity: spy })('dummyEntityName', 'dummyId', [
            'empty field',
            'empty field 2',
        ]);

        expect(result).toBeNull();
    });

    it('getApiError', () => {
        const entity = {
            getEntityName: () => 'dummyEntityName',
            id: 'dummyId',
        };
        const field = '0.age';

        const spy = jest.spyOn({
            getApiErrorFromPath: () => {
                return {};
            },
        }, 'getApiErrorFromPath');
        spy.mockReturnValue({ selfLink: 'dummy.expression' });

        const result = instance.getters.getApiError(state, { getApiErrorFromPath: spy })(entity, field);

        expect(result).toEqual({ selfLink: 'dummy.expression' });
    });

    it('getSystemConfigApiError', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return {
                    dummyKey: { error: 'dummy error' },
                };
            },
        }, 'getErrorsForEntity');

        const result = instance.getters.getSystemConfigApiError(state, { getErrorsForEntity: spy })('dummySystemConfig', 'dummySaleChannelId', 'dummyKey');

        expect(result).toEqual({ error: 'dummy error' });
    });

    it('getSystemConfigApiError with null entity name', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return {};
            },
        }, 'getErrorsForEntity');

        const result = instance.getters.getSystemConfigApiError(state, { getErrorsForEntity: spy })('empty entity name', 'dummySaleChannelId', 'dummyKey');

        expect(result).toBeNull();
    });

    it('getSystemConfigApiError with null key', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return {
                    dummyKey: { error: 'dummy error' },
                };
            },
        }, 'getErrorsForEntity');

        const result = instance.getters.getSystemConfigApiError(state, { getErrorsForEntity: spy })('dummySystemConfig', 'dummySaleChannelId', 'empty key');

        expect(result).toBeNull();
    });

    it('getSystemConfigApiError with null entity name and sale channel ud', () => {
        const spy = jest.spyOn({
            getErrorsForEntity: () => {
                return null;
            },
        }, 'getErrorsForEntity');

        const result = instance.getters.getSystemConfigApiError(state, { getErrorsForEntity: spy })('dummySystemConfig', 'dummySaleChannelId', 'dummyKey');

        expect(result).toBeNull();
    });

    it('getAllApiErrors', () => {
        const result = instance.getters.getAllApiErrors(state)();

        const expected = [
            { dummyId: { 0: { age: { selfLink: 'dummy.expression' } }, dummyField: { selfLink: 'dummy.expression' } } },
            { dummySaleChannelId: { dummyKey: { error: 'dummy error' } } },
        ];

        expect(result).toEqual(expected);
    });

    it('getSystemError', () => {
        const result = instance.getters.getSystemError(state)('dummy id');

        expect(result).toEqual({});
    });

    it('existsErrorInProperty', () => {
        const result = instance.getters.existsErrorInProperty(state)('dummyEntityName', '0/age');

        expect(result).toBeTruthy();
    });

    it('existsErrorInProperty with empty entity', () => {
        const result = instance.getters.existsErrorInProperty(state)('empty entity', 'dummyId');

        expect(result).toBeFalsy();
    });

    it('countSystemError', () => {
        const result = instance.getters.countSystemError(state)();

        expect(result).toBe(1);
    });
});
