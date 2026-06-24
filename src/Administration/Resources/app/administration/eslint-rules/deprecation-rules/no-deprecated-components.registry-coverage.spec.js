const { loadRegistry } = require('./registry/load-registry');

describe('trunk component deprecation coverage', () => {
    it('keeps every trunk mt component deprecation in the registry', () => {
        const expectedDeprecations = [
            ['sw-alert-deprecated', 'mt-banner'],
            ['sw-alert', 'mt-banner'],
            ['sw-button-deprecated', 'mt-button'],
            ['sw-button', 'mt-button'],
            ['sw-card', 'mt-card'],
            ['sw-icon', 'mt-icon'],
            ['sw-checkbox-field', 'mt-checkbox'],
            ['sw-colorpicker', 'mt-colorpicker'],
            ['sw-datepicker', 'mt-datepicker'],
            ['sw-email-field', 'mt-email-field'],
            ['sw-number-field', 'mt-number-field'],
            ['sw-password-field', 'mt-password-field'],
            ['sw-select-field', 'mt-select'],
            ['sw-select-number-field', 'mt-select'],
            ['sw-switch-field', 'mt-switch'],
            ['sw-text-editor', 'mt-text-editor'],
            ['sw-text-editor-link-menu', 'mt-text-editor'],
            ['sw-text-editor-table-toolbar', 'mt-text-editor'],
            ['sw-text-editor-toolbar-button', 'mt-text-editor'],
            ['sw-text-editor-toolbar-table-button', 'mt-text-editor'],
            ['sw-text-editor-toolbar', 'mt-text-editor'],
            ['sw-text-field-deprecated', 'mt-text-field'],
            ['sw-text-field', 'mt-text-field'],
            ['sw-url-field', 'mt-url-field'],
        ];

        const migrationsByComponent = new Map(
            loadRegistry().componentApiMigrations.map((migration) => [
                migration.component,
                migration,
            ]),
        );

        expectedDeprecations.forEach(([
            component,
            replacement,
        ]) => {
            expect(migrationsByComponent.get(component)?.replacement).toBe(replacement);
        });
    });
});
