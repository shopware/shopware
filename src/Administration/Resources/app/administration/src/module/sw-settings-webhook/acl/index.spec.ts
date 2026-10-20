/**
 * @sw-package framework
 */
const addPrivilegeMappingEntry = jest.fn();
const originalService = Shopware.Service;

describe('src/module/sw-settings-webhook/acl', () => {
    beforeAll(() => {
        // @ts-expect-error - only the privileges service is needed
        Shopware.Service = () => ({ addPrivilegeMappingEntry });
    });

    beforeEach(async () => {
        jest.resetModules();

        await import('./index');
    });

    afterAll(() => {
        Shopware.Service = originalService;
    });

    it('maps the webhook roles to the webhook privileges', () => {
        expect(addPrivilegeMappingEntry).toHaveBeenCalledTimes(1);
        expect(addPrivilegeMappingEntry).toHaveBeenCalledWith({
            category: 'permissions',
            parent: 'settings',
            key: 'webhook',
            roles: {
                viewer: {
                    privileges: [
                        'webhook:read',
                        'app:read',
                        'user:read',
                        'integration:read',
                        'acl_role:read',
                    ],
                    dependencies: [],
                },
                editor: {
                    privileges: ['webhook:update'],
                    dependencies: ['webhook.viewer'],
                },
                deleter: {
                    privileges: ['webhook:delete'],
                    dependencies: ['webhook.viewer'],
                },
            },
        });
    });
});
