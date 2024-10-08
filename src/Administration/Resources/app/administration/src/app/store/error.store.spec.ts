import { createPinia, setActivePinia } from 'pinia';
import ShopwareError from 'src/core/data/ShopwareError';
import type { ErrorStore } from './error.store';
import 'src/app/store/error.store';

describe('error.store', () => {
    let store: ErrorStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Shopware.Store.get('error');
    });

    it('has initial state', () => {
        expect(store.api).toStrictEqual({});
        expect(store.system).toStrictEqual({});
    });

    describe('actions', () => {
        describe('addApiError', () => {
            it('adds an API error', () => {
                const error = new ShopwareError({ code: 'TEST-001', detail: 'Test error' });
                store.addApiError({ expression: 'entity.field', error });
                // @ts-expect-error
                expect(store.api.entity.field).toEqual({ ...error, selfLink: 'entity.field' });
            });

            it('adds a nested API error', () => {
                const error = new ShopwareError({ code: 'TEST-002', detail: 'Nested error' });
                store.addApiError({ expression: 'entity.nested.field', error });
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                expect(store.api.entity.nested.field).toEqual({ ...error, selfLink: 'entity.nested.field' });
            });
        });

        describe('removeApiError', () => {
            it('removes an API error', () => {
                const error = new ShopwareError({ code: 'TEST-003', detail: 'Test error' });
                store.addApiError({ expression: 'entity.field', error });
                // @ts-expect-error
                expect(store.api.entity.field).toBeDefined();
                store.removeApiError('entity.field');
                // @ts-expect-error
                expect(store.api.entity.field).toBeUndefined();
            });

            it('removes a nested API error', () => {
                const error = new ShopwareError({ code: 'TEST-004', detail: 'Nested error' });
                store.addApiError({ expression: 'entity.nested.field', error });
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                expect(store.api.entity.nested.field).toBeDefined();
                store.removeApiError('entity.nested.field');
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                expect(store.api.entity.nested.field).toBeUndefined();
            });
        });

        describe('resetApiErrors', () => {
            it('resets all API errors', () => {
                store.addApiError({
                    expression: 'entity1.field',
                    error: new ShopwareError({ code: 'TEST-005', detail: 'Error 1' }),
                });
                store.addApiError({
                    expression: 'entity2.field',
                    error: new ShopwareError({ code: 'TEST-006', detail: 'Error 2' }),
                });
                store.resetApiErrors();
                expect(store.api).toStrictEqual({});
            });
        });

        describe('addSystemError', () => {
            it('adds a system error with generated ID', () => {
                const error = new ShopwareError({ code: 'SYS-001', detail: 'System error' });
                store.addSystemError({ error });
                expect(Object.values(store.system)).toHaveLength(1);
                expect(Object.values(store.system)[0]).toEqual(error);
            });

            it('adds a system error with provided ID', () => {
                const error = new ShopwareError({ code: 'SYS-002', detail: 'System error' });
                store.addSystemError({ error, id: 'custom-id' });
                expect(store.system['custom-id']).toEqual(error);
            });
        });

        describe('removeSystemError', () => {
            it('removes a system error', () => {
                const error = new ShopwareError({ code: 'SYS-003', detail: 'System error' });
                store.addSystemError({ error, id: 'test-id' });
                store.removeSystemError('test-id');
                expect(store.system['test-id']).toBeUndefined();
            });
        });
    });

    describe('getters', () => {
        describe('getErrorsForEntity', () => {
            it('returns null if no errors exist for the entity', () => {
                expect(store.getErrorsForEntity('nonexistent', '1')).toBeNull();
            });

            it('returns errors for a specific entity', () => {
                const error = new ShopwareError({ code: 'ENT-001', detail: 'Entity error' });
                store.addApiError({ expression: 'testEntity.1.field', error });
                expect(store.getErrorsForEntity('testEntity', '1')).toEqual({ field: error });
            });
        });

        describe('getApiErrorFromPath', () => {
            it('returns null if no error exists at the path', () => {
                expect(
                    store.getApiErrorFromPath('entity', 'id', [
                        'nonexistent',
                        'path',
                    ]),
                ).toBeNull();
            });

            it('returns the error at the specified path', () => {
                const error = new ShopwareError({ code: 'PATH-001', detail: 'Nested error' });
                store.addApiError({ expression: 'entity.id.nested.field', error });
                expect(
                    store.getApiErrorFromPath('entity', 'id', [
                        'nested',
                        'field',
                    ]),
                ).toEqual(error);
            });
        });

        describe('getApiError', () => {
            it('returns the error for a given entity and field', () => {
                const error = new ShopwareError({ code: 'FIELD-001', detail: 'Field error' });
                store.addApiError({ expression: 'testEntity.123.testField', error });
                const entity = { getEntityName: () => 'testEntity', id: '123' };
                expect(store.getApiError(entity, 'testField')).toEqual(error);
            });
        });

        describe('getSystemConfigApiError', () => {
            it('returns null if no error exists', () => {
                expect(store.getSystemConfigApiError('entity', 'channel', 'key')).toBeNull();
            });

            it('returns the system config API error', () => {
                const error = new ShopwareError({ code: 'CONFIG-001', detail: 'Config error' });
                store.addApiError({ expression: 'entity.channel.key', error });
                expect(store.getSystemConfigApiError('entity', 'channel', 'key')).toEqual(error);
            });
        });

        describe('getAllApiErrors', () => {
            it('returns all API errors', () => {
                store.addApiError({
                    expression: 'entity1.id.field',
                    error: new ShopwareError({ code: 'ALL-001', detail: 'Error 1' }),
                });
                store.addApiError({
                    expression: 'entity2.id.field',
                    error: new ShopwareError({ code: 'ALL-002', detail: 'Error 2' }),
                });
                const allErrors = store.getAllApiErrors();
                expect(allErrors).toHaveLength(2);
                expect(allErrors).toEqual(
                    expect.arrayContaining([
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        expect.objectContaining({ id: { field: expect.objectContaining({ code: 'ALL-001' }) } }),
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        expect.objectContaining({ id: { field: expect.objectContaining({ code: 'ALL-002' }) } }),
                    ]),
                );
            });
        });

        describe('existsErrorInProperty', () => {
            it('returns false if no errors exist for the entity', () => {
                expect(store.existsErrorInProperty('nonexistent', ['field'])).toBeFalsy();
            });

            it('returns true if an error exists in the specified property', () => {
                store.addApiError({
                    expression: 'entity.id.field',
                    error: new ShopwareError({ code: 'PROP-001', detail: 'Error' }),
                });
                expect(store.existsErrorInProperty('entity', ['field'])).toBeTruthy();
            });
        });

        describe('getSystemError', () => {
            it('returns null if no system error exists with the given ID', () => {
                expect(store.getSystemError('nonexistent')).toBeNull();
            });

            it('returns the system error with the given ID', () => {
                const error = new ShopwareError({ code: 'SYS-004', detail: 'System error' });
                store.addSystemError({ error, id: 'test-id' });
                expect(store.getSystemError('test-id')).toEqual(error);
            });
        });

        describe('countSystemError', () => {
            it('returns the correct count of system errors', () => {
                expect(store.countSystemError()).toBe(0);
                store.addSystemError({ error: new ShopwareError({ code: 'COUNT-001', detail: 'Error 1' }) });
                store.addSystemError({ error: new ShopwareError({ code: 'COUNT-002', detail: 'Error 2' }) });
                expect(store.countSystemError()).toBe(2);
            });
        });
    });
});
