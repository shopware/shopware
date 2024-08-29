/**
 * @package admin
 * @group disabledCompat
 */
import ErrorResolver from 'src/core/data/error-resolver.data';
import EntityFactory from 'src/core/data/entity-factory.data';

const entityFactory = new EntityFactory();

describe('src/core/data/error-resolver.data', () => {
    let errorResolver;

    beforeEach(() => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        errorResolver = new ErrorResolver();
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    describe('resetApiErrors', () => {
        it('should dispatches "error/resetApiErrors" action', () => {
            errorResolver.resetApiErrors();

            expect(Shopware.State.dispatch).toHaveBeenCalledWith('error/resetApiErrors');
        });
    });

    describe('handleWriteErrors', () => {
        it('should throws an error if no errors are provided', () => {
            expect(() => {
                errorResolver.handleWriteErrors({});
            }).toThrow('[error-resolver] handleWriteError was called without errors');
        });

        it('should handles write errors and adds system errors', () => {
            const errors = [
                { source: { pointer: '/0/firstName' }, code: 'CODE1' },
                { source: { pointer: '/0/lastName' }, code: 'CODE2' },
                { source: { pointer: '/0/translations/123123' }, code: 'CODE2' },
                { source: { pointer: '' }, message: 'System Error', code: 'CODE4' },
            ];

            const changeset = [
                {
                    entity: entityFactory.create('customer'),
                    changes: [{
                        firstName: 'a',
                        lastName: 'b',
                    }],
                },
                {
                    entity: entityFactory.create('customer'),
                    changes: [{
                        firstName: 'c',
                        lastName: 'd',
                    }],
                },
            ];

            errorResolver.handleWriteErrors(changeset, { errors });

            expect(Shopware.State.dispatch).toHaveBeenCalledTimes(3);
            expect(Shopware.State.dispatch).toHaveBeenNthCalledWith(1, 'error/addApiError', {
                expression: expect.anything(),
                error: expect.any(Shopware.Classes.ShopwareError),
            });
            expect(Shopware.State.dispatch).toHaveBeenNthCalledWith(2, 'error/addApiError', {
                expression: expect.anything(),
                error: expect.any(Shopware.Classes.ShopwareError),
            });
            expect(Shopware.State.dispatch).toHaveBeenNthCalledWith(3, 'error/addSystemError', expect.any(Shopware.Classes.ShopwareError));
        });

        it('should convert to ShopwareError', () => {
            const errors = [
                { source: { pointer: '/0/firstName' }, code: 'CODE1' },
            ];

            const changeset = [{
                entity: entityFactory.create('customer'),
                changes: [{
                    firstName: 'a',
                }],
            }];

            errorResolver.reduceErrorsByWriteIndex = jest.fn().mockReturnValue({
                system: [],
                0: {
                    firstName: {
                        code: 'CODE1',
                    },
                },
            });


            errorResolver.handleWriteErrors(changeset, { errors });

            expect(errorResolver.reduceErrorsByWriteIndex).toHaveBeenCalledTimes(1);
            expect(Shopware.State.dispatch).toHaveBeenNthCalledWith(1, 'error/addApiError', {
                expression: expect.anything(),
                error: expect.any(Shopware.Classes.ShopwareError),
            });
        });
    });

    describe('getErrorPath', () => {
        it('should returns the correct error path', () => {
            const entity = {
                getEntityName: jest.fn(() => 'product'), id: 'abc123',
            };
            const currentField = 'name';

            const result = errorResolver.getErrorPath(entity, currentField);

            expect(result).toBe('product.abc123.name');
        });
    });

    describe('handleDeleteError', () => {
        it('should handle delete errors and add system errors and api errors', () => {
            const errors = [{
                error: {
                    code: 'SOME_ERROR_CODE',
                    detail: '1',
                    parameters: {
                        '{{ parameter }}': 'Test Parameter',
                    },
                },
                entityName: 'Entity1',
                id: '1',
            }, {
                error: {
                    code: 'SOME_ERROR_CODE',
                    detail: '2',
                    parameters: {
                        '{{ parameter }}': 'Test Parameter',
                    },
                },
                entityName: 'Entity2',
                id: '2',
            }];

            errorResolver.handleDeleteError(errors);

            expect(Shopware.State.dispatch).toHaveBeenCalledWith('error/addSystemError', {
                error: expect.any(Shopware.Classes.ShopwareError),
            });
            expect(Shopware.State.dispatch).toHaveBeenCalledWith('error/addApiError', {
                expression: 'Entity1.1', error: expect.any(Shopware.Classes.ShopwareError),
            });
            expect(Shopware.State.dispatch).toHaveBeenCalledWith('error/addApiError', {
                expression: 'Entity2.2', error: expect.any(Shopware.Classes.ShopwareError),
            });
        });
    });
});
