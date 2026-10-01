/**
 * @sw-package framework
 */
import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';
import api from './extension-api';

describe('src/core/extension-api.ts', () => {
    const actionNames = {
        reject: 'extension-api-spec-reject',
        resolveSync: 'extension-api-spec-resolve-sync',
        resolveAsync: 'extension-api-spec-resolve-async',
        noPrivileges: 'extension-api-spec-no-privileges',
    };

    it('should reject handle with missing privileges', async () => {
        global.activeAclRoles = [];

        const spy = jest.fn();
        spy.mockImplementation(() => {
            throw new Error('I should never run');
        });
        const destroyHandle = api.handle(actionNames.reject, spy);

        await expect(
            send(actionNames.reject, {
                message: 'foo',
                privileges: ['read:user'],
            }),
        ).rejects.toThrow(/Your app is missing the privileges.*read:user/);
        expect(spy).not.toHaveBeenCalled();

        destroyHandle();
    });

    it('should resolve handle with existing privileges synchronously', async () => {
        global.activeAclRoles = ['read:user'];

        const spy = jest.fn();
        spy.mockImplementation(() => {
            return 'UUID';
        });
        const destroyHandle = api.handle(actionNames.resolveSync, spy);

        await expect(
            send(actionNames.resolveSync, {
                message: 'foo',
                privileges: ['read:user'],
            }),
        ).resolves.toBe('UUID');
        expect(spy).toHaveBeenCalledTimes(1);

        destroyHandle();
    });

    it('should resolve handle with existing privileges asynchronously', async () => {
        global.activeAclRoles = ['read:user'];

        const spy = jest.fn();
        spy.mockImplementation(() => {
            return Promise.resolve('UUID');
        });
        const destroyHandle = api.handle(actionNames.resolveAsync, spy);

        const result = send(actionNames.resolveAsync, {
            message: 'foo',
            privileges: ['read:user'],
        });
        await flushPromises();

        expect(result).toBeInstanceOf(Promise);
        expect(spy).toHaveBeenCalledTimes(1);
        await expect(result).resolves.toBe('UUID');

        destroyHandle();
    });

    it('should call original method directly without privileges', async () => {
        global.activeAclRoles = ['read:user'];
        const canMock = jest.fn();
        Shopware.Service('acl').can = canMock;

        const spy = jest.fn();
        spy.mockImplementation(() => {
            return 'UUID';
        });
        const destroyHandle = api.handle(actionNames.noPrivileges, spy);

        await expect(
            send(actionNames.noPrivileges, {
                message: 'foo',
            }),
        ).resolves.toBe('UUID');
        expect(spy).toHaveBeenCalledTimes(1);
        expect(canMock).toHaveBeenCalledTimes(0);

        destroyHandle();
    });
});
