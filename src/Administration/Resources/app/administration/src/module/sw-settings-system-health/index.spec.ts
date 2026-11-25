/**
 * @sw-package framework
 */
import './index';

jest.mock('./acl', () => ({}));

describe('src/module/sw-settings-system-health', () => {
    it('should protect the page and its settings tile with the system health permission', () => {
        const module = Shopware.Module.getModuleRegistry().get('sw-settings-system-health');

        expect(module?.routes.get('sw.settings.system.health.index')?.meta).toEqual(
            expect.objectContaining({ privilege: 'system.system_health' }),
        );
        expect(module?.manifest.settingsItem).toEqual([expect.objectContaining({ privilege: 'system.system_health' })]);
    });
});
