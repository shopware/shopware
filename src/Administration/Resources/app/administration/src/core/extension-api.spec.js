import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';
import MissingPrivilegesError from '@shopware-ag/meteor-admin-sdk/es/_internals/privileges/missing-privileges-error';
import api from './extension-api';

describe('src/core/extension-api.ts', () => {
    it('should reject handle with missing privileges', async () => {
        // Setup acl roles
        global.activeAclRoles = [];

        // Handle 'jest' message and provide spy for assertion
        const spy = jest.fn();
        spy.mockImplementation(() => {
            throw new Error('I should never run');
        });
        const destroyHandle = api.handle('jest', spy);

        // Send handled message with privileges
        await expect(send('jest', {
            message: 'foo',
            privileges: [
                'read:user',
            ],
        })).rejects.toThrow(new MissingPrivilegesError('jest', ['read:user']));
        expect(spy).not.toHaveBeenCalled();

        destroyHandle();
    });

    it('should resolve handle with existing privileges synchronously', async () => {
        // Setup acl roles
        global.activeAclRoles = ['read:user'];

        // Handle 'jest' message and provide spy for assertion
        const spy = jest.fn();
        spy.mockImplementation(() => {
            return 'UUID';
        });
        const destroyHandle = api.handle('jest', spy);

        // Send handled message with privileges
        await expect(send('jest', {
            message: 'foo',
            privileges: [
                'read:user',
            ],
        })).resolves.toBe('UUID');
        expect(spy).toHaveBeenCalledTimes(1);

        destroyHandle();
    });

    it('should resolve handle with existing privileges asynchronously', async () => {
        // Setup acl roles
        global.activeAclRoles = ['read:user'];

        // Handle 'jest' message and provide spy for assertion
        const spy = jest.fn();
        spy.mockImplementation(() => {
            return Promise.resolve('UUID');
        });
        const destroyHandle = api.handle('jest', spy);

        // Send handled message with privileges
        const result = send('jest', {
            message: 'foo',
            privileges: [
                'read:user',
            ],
        });
        await flushPromises();

        expect(result).toBeInstanceOf(Promise);
        expect(spy).toHaveBeenCalledTimes(1);
        await expect(result).resolves.toBe('UUID');

        destroyHandle();
    });

    it('should call original method directly without privileges', async () => {
        // Setup acl roles
        global.activeAclRoles = ['read:user'];
        const canMock = jest.fn();
        Shopware.Service('acl').can = canMock;

        // Handle 'jest' message and provide spy for assertion
        const spy = jest.fn();
        spy.mockImplementation(() => {
            return 'UUID';
        });
        const destroyHandle = api.handle('jest', spy);

        // Send handled message with privileges
        await expect(send('jest', {
            message: 'foo',
        })).resolves.toBe('UUID');
        expect(spy).toHaveBeenCalledTimes(1);
        expect(canMock).toHaveBeenCalledTimes(0);

        destroyHandle();
    });
});
