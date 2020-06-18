import AclService from 'src/app/service/acl.service';

describe('src/app/service/acl.service.js', () => {
    it('should be an admin', () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: true } })
        });

        expect(aclService.isAdmin()).toBeTruthy();
    });

    it('should not be an admin', () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } })
        });

        expect(aclService.isAdmin()).toBeFalsy();
    });

    it('should allow every privilege as an admin', () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: true } }),
            getters: {
                userPrivileges: []
            }
        });

        expect(aclService.can('system.clear_cache')).toBeTruthy();
    });

    it('should disallow when privilege does not exists', () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
            getters: {
                userPrivileges: []
            }
        });

        expect(aclService.can('system.clear_cache')).toBeFalsy();
    });

    it('should allow when privilege exists', () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
            getters: {
                userPrivileges: ['system.clear_cache']
            }
        });

        expect(aclService.can('system.clear_cache')).toBeTruthy();
    });

    it('should return all privileges', () => {
        const aclService = new AclService({
            get: () => ({ currentUser: { admin: false } }),
            getters: {
                userPrivileges: [
                    'system.clear_cache',
                    'orders.create_discounts'
                ]
            }
        });

        expect(aclService.privileges).toContain('system.clear_cache');
        expect(aclService.privileges).toContain('orders.create_discounts');
    });
});
