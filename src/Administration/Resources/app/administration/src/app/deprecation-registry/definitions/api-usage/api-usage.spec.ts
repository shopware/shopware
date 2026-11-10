/**
 * @sw-package framework
 */
import migrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';

describe('src/app/deprecation-registry/definitions/api-usage', () => {
    it('has well-formed migrations', () => {
        expectWellFormedMigrations(migrations);
    });

    it('rewrites only with the replace steps and the module snippets step', () => {
        migrations
            .flatMap((migration) => migration.usage)
            .forEach((usage) => {
                expect(usage.fix).toBe(
                    [
                        'replace-component-member',
                        'replace-component-method-call',
                        'module-snippets',
                    ].includes(usage.kind)
                        ? 'auto'
                        : 'manual',
                );
            });
    });
});
