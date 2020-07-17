import PrivilegesService from 'src/app/service/privileges.service';

describe('src/app/service/acl.service.js', () => {
    beforeEach(() => {
        global.console.warn = jest.fn();
    });

    afterEach(() => {
        global.console.warn.mockReset();
    });

    it('should contain no privilege mappings', () => {
        const privilegesService = new PrivilegesService();

        expect(privilegesService.getPrivilegesMappings().length).toBe(0);
    });

    it('should add a privilege mapping', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMapping = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMapping);

        expect(privilegesService.getPrivilegesMappings().length).toBe(1);
        expect(privilegesService.getPrivilegesMappings()[0]).toStrictEqual(privilegeMapping);
    });

    it('should throw a warning if the argument is not an object', () => {
        const privilegesService = new PrivilegesService();

        expect(global.console.warn).not.toHaveBeenCalled();
        privilegesService.addPrivilegeMappingEntry('notAnObject');
        expect(global.console.warn).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntry]',
            'The privilegeMapping has to be an object.',
        );

        expect(privilegesService.getPrivilegesMappings().length).toBe(0);
    });

    it('should throw a warning if the property category is missing', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMapping = {
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        expect(global.console.warn).not.toHaveBeenCalled();
        privilegesService.addPrivilegeMappingEntry(privilegeMapping);
        expect(global.console.warn).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntry]',
            'The privilegeMapping need the property "category".',
        );

        expect(privilegesService.getPrivilegesMappings().length).toBe(0);
    });

    it('should throw a warning if the property parent is missing', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMapping = {
            category: 'additional_permissions',
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        expect(global.console.warn).not.toHaveBeenCalled();
        privilegesService.addPrivilegeMappingEntry(privilegeMapping);
        expect(global.console.warn).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntry]',
            'The privilegeMapping need the property "parent".',
        );

        expect(privilegesService.getPrivilegesMappings().length).toBe(0);
    });

    it('should throw a warning if the property key is missing', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMapping = {
            category: 'additional_permissions',
            parent: null,
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        expect(global.console.warn).not.toHaveBeenCalled();
        privilegesService.addPrivilegeMappingEntry(privilegeMapping);
        expect(global.console.warn).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntry]',
            'The privilegeMapping need the property "key".',
        );

        expect(privilegesService.getPrivilegesMappings().length).toBe(0);
    });

    it('should add multiple privilege mappings', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'orders',
            roles: {
                create_discounts: {
                    privileges: ['order:create:discount'],
                    dependencies: []
                }
            }
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        expect(privilegesService.getPrivilegesMappings().length).toBe(2);
        expect(privilegesService.getPrivilegesMappings()[0]).toStrictEqual(privilegeMappingOne);
        expect(privilegesService.getPrivilegesMappings()[1]).toStrictEqual(privilegeMappingTwo);
    });

    it('should merge multiple privileges with same category and key', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: []
                }
            }
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        const privilegeMappings = privilegesService.getPrivilegesMappings();
        expect(privilegeMappings.length).toBe(1);

        expect(privilegeMappings[0].roles).toMatchObject({
            clear_cache: {
                privileges: ['system:clear:cache'],
                dependencies: []
            },
            core_update: {
                privileges: ['system:core:update'],
                dependencies: []
            }
        });
    });

    it('should return the privilege with all roles', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: []
                }
            }
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        expect(privilegesService.getPrivilege('system.core_update')).toMatchObject({
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                },

                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: []
                }
            }
        });
    });

    it('should return the exact privilege role', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: []
                }
            }
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        expect(privilegesService.getPrivilegeRole('system.core_update')).toMatchObject({
            privileges: ['system:core:update'],
            dependencies: []
        });

        expect(privilegesService.getPrivilegeRole('system.clear_cache')).toMatchObject({
            privileges: ['system:clear:cache'],
            dependencies: []
        });
    });

    it('should return undefined when the exact privilege role does not exists', () => {
        const privilegesService = new PrivilegesService();

        expect(privilegesService.getPrivilegeRole('does.not_exists')).toBe(undefined);
    });

    it('should check if the privilege exists', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: []
                }
            }
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        expect(privilegesService.existsPrivilege('system.core_update')).toBeTruthy();
        expect(privilegesService.existsPrivilege('system.not_exists')).toBeFalsy();
    });

    it('should filter only matching privileges', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: []
                }
            }
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        const testPrivileges = [
            'system.clear_cache',
            'system:clear:cache',
            'orders:read'
        ];

        expect(privilegesService.filterPrivilegesRoles(testPrivileges)).toContain('system.clear_cache');
        expect(privilegesService.filterPrivilegesRoles(testPrivileges)).not.toContain('system:clear:cache.');
        expect(privilegesService.filterPrivilegesRoles(testPrivileges)).not.toContain('orders:read.');
    });

    it('should filter only matching privileges and duplicates', () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: []
                }
            }
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: []
                }
            }
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        const testPrivileges = [
            'system.clear_cache',
            'system.clear_cache'
        ];

        expect(privilegesService.filterPrivilegesRoles(testPrivileges)).toStrictEqual([
            'system.clear_cache'
        ]);
    });
});
