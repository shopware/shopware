import PrivilegesService from 'src/app/service/privileges.service';

Shopware.Application.addServiceProvider('privileges', () => {
    return new PrivilegesService();
});

describe('src/module/sw-settings-state-machine/index.js', () => {
    import('src/module/sw-settings-state-machine');

    it('should add privilege mapping', () => {
        const privilegesMappings = Shopware.Service('privileges').getPrivilegesMappings();
        const privilegeMapping = privilegesMappings.find((entry) => entry.key === 'state_machine');

        expect(privilegeMapping !== undefined).toBe(true);
        expect(privilegeMapping.hasOwnProperty('roles')).toBe(true);

        [
            'viewer',
            'editor',
        ].forEach((role) => {
            expect(privilegeMapping.roles.hasOwnProperty(role)).toBe(true);
            expect(privilegeMapping.roles[role].hasOwnProperty('privileges')).toBe(true);
            expect(privilegeMapping.roles[role].privileges.length).toBeGreaterThan(0);
        });
    });

    it('should register components', () => {
        const componentRegistry = Shopware.Component.getComponentRegistry();

        expect(componentRegistry.has('sw-settings-state-machine-list')).toBe(true);
        expect(componentRegistry.has('sw-settings-state-machine-detail')).toBe(true);
        expect(componentRegistry.has('sw-settings-state-machine-state-list')).toBe(true);
        expect(componentRegistry.has('sw-settings-state-machine-state-detail')).toBe(true);
    });

    it('should register module', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-settings-state-machine');

        expect(module !== undefined).toBe(true);
        expect(module.manifest.type).toBe('core');
        expect(module.manifest.name).toBe('settings-state-machine');
        expect(module.routes.size).toBe(2);

        const indexRoute = module.routes.get('sw.settings.state.machine.index');
        expect(indexRoute !== undefined).toBe(true);
        expect(indexRoute.path).toBe('/sw/settings/state/machine/index');

        const detailRoute = module.routes.get('sw.settings.state.machine.detail');
        expect(detailRoute !== undefined).toBe(true);
        expect(detailRoute.path).toBe('/sw/settings/state/machine/detail/:id');

        const props = detailRoute.props.default({ params: { id: 'foo' } });
        expect(props.hasOwnProperty('stateMachineId')).toBe(true);
        expect(props.stateMachineId).toBe('foo');
    });
});
