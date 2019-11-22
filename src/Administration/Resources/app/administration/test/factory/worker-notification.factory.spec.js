import WorkerNotificationFactory from 'src/core/factory/worker-notification.factory';
import MiddlewareHelper from 'src/core/helper/middleware.helper';

beforeEach(() => {
    const registry = WorkerNotificationFactory.getRegistry();
    registry.clear();

    WorkerNotificationFactory.resetHelper();
});

describe('core/factory/worker-notification.factory.js', () => {
    const noop = jest.fn((next) => {
        next();
    });

    it('should return the registry', () => {
        expect(WorkerNotificationFactory.getRegistry() instanceof Map).toBeTruthy();
    });

    it('should initialize the middleware helper', () => {
        WorkerNotificationFactory.register('foo', {
            name: 'foo',
            fn: noop
        });

        const helper = WorkerNotificationFactory.initialize();
        expect(helper instanceof MiddlewareHelper).toBeTruthy();
        const sameHelper = WorkerNotificationFactory.initialize();
        expect(sameHelper instanceof MiddlewareHelper).toBeTruthy();
        expect(sameHelper).toBe(helper);
    });

    it('should fire the registered worker notification middleware', (done) => {
        const callback = jest.fn((next) => {
            expect(callback).toHaveBeenCalled();
            next();
            done();
        });

        WorkerNotificationFactory.register('bar', {
            name: 'bar',
            fn: callback
        });

        WorkerNotificationFactory.register('foo', {
            name: 'foo',
            fn: callback
        });

        const helper = WorkerNotificationFactory.initialize();
        helper.go({
            queue: [
                { name: 'batz', stack: 1 },
                { name: 'foo', stack: 1 }
            ]
        });
    });

    describe('register worker notification', () => {
        it('should register using an unique name', () => {
            const result = WorkerNotificationFactory.register('foo', {
                name: 'foo',
                fn: noop
            });

            expect(result).toBeTruthy();
        });

        it('should reject the registration using the same name', () => {
            expect(WorkerNotificationFactory.register('foo', {
                name: 'foo',
                fn: noop
            })).toBeTruthy();

            expect(WorkerNotificationFactory.register('foo', {
                name: 'foo',
                fn: noop
            })).toBeFalsy();
        });

        it('should reject the registration if the options object is not valid', () => {
            expect(WorkerNotificationFactory.register('', {})).toBeFalsy();

            expect(WorkerNotificationFactory.register('foo', {
                fn: noop
            })).toBeFalsy();

            expect(WorkerNotificationFactory.register('foo', {
                name: '',
                fn: noop
            })).toBeFalsy();

            expect(WorkerNotificationFactory.register('foo', {
                name: 'foo'
            })).toBeFalsy();

            expect(WorkerNotificationFactory.register('foo', {
                name: 'foo',
                fn: { foo: 'bar' }
            })).toBeFalsy();
        });
    });

    describe('override worker notification', () => {
        it('should override an existing worker notification', () => {
            WorkerNotificationFactory.register('foo', {
                name: 'foo',
                fn: noop
            });

            expect(WorkerNotificationFactory.override('foo', {
                name: 'bar',
                fn: noop
            })).toBeTruthy();

            const registryEntry = WorkerNotificationFactory.getRegistry().get('foo');
            expect(registryEntry.name).toBe('bar');
        });

        it('should reject the override if no worker notification with the same name is registered', () => {
            expect(WorkerNotificationFactory.override('foo', {
                name: 'foo',
                fn: noop
            })).toBeFalsy();
        });

        it('should reject the override if the options are not valid', () => {
            expect(WorkerNotificationFactory.register('foo', {
                name: 'foo',
                fn: noop
            })).toBeTruthy();
            expect(WorkerNotificationFactory.override('')).toBeFalsy();
            expect(WorkerNotificationFactory.override('', {})).toBeFalsy();
            expect(WorkerNotificationFactory.override('foo', {})).toBeFalsy();
            expect(WorkerNotificationFactory.override('foo', {
                name: ''
            })).toBeFalsy();

            expect(WorkerNotificationFactory.override('foo', {
                name: 'foo',
                fn: { foo: 'bar' }
            })).toBeFalsy();
        });
    });

    describe('remove worker notification', () => {
        it('should remove an existing worker notification', () => {
            WorkerNotificationFactory.register('foo', {
                name: 'foo',
                fn: noop
            });
            expect(WorkerNotificationFactory.remove('foo')).toBeTruthy();
            expect(WorkerNotificationFactory.getRegistry().size).toBe(0);
        });

        it('should reject the removal of an non existing worker notification', () => {
            expect(WorkerNotificationFactory.remove('foo')).toBeFalsy();
            expect(WorkerNotificationFactory.getRegistry().size).toBe(0);
        });

        it('should reject the removal if the options are not valid', () => {
            expect(WorkerNotificationFactory.remove('')).toBeFalsy();
            expect(WorkerNotificationFactory.remove()).toBeFalsy();
        });
    });
});
