/**
 * @sw-package framework
 */
import migrations from './index';
import { expectWellFormedMigrations } from '../helpers/test-utils';
import type { ComponentMemberStep, JsApiMigration } from '../helpers';

/** The fields that tell what a step matches, per step kind. */
function matchedSource(usage: JsApiMigration['usage'][number]): Record<string, string> {
    switch (usage.kind) {
        case 'service-method':
            return { service: usage.service, method: usage.method };
        case 'store-member':
            return { store: usage.store, member: usage.member };
        case 'component-member':
            return { member: usage.member };
        case 'component-reference':
            return { component: usage.component };
        case 'config-file':
            return { pattern: usage.pattern };
        default:
            return { from: usage.from };
    }
}

describe('src/app/deprecation-registry/definitions/js-api', () => {
    it('has well-formed migrations', () => {
        expectWellFormedMigrations(migrations);
    });

    it('describes every usage step with a known kind and a source to match', () => {
        migrations.forEach((migration) => {
            expect(migration.usage.length).toBeGreaterThan(0);

            migration.usage.forEach((usage) => {
                expect([
                    'replace-object-option',
                    'member-call',
                    'member-usage',
                    'service-method',
                    'store-member',
                    'component-member',
                    'inject-key',
                    'component-reference',
                    'string-value',
                    'object-key',
                    'import-source',
                    'config-file',
                ]).toContain(usage.kind);
                expect(Object.values(matchedSource(usage)).join('')).toMatch(/\S/);
            });
        });
    });

    it('limits every component member step to components and fixes only with a script replacement', () => {
        const componentMembers = migrations
            .flatMap((migration) => migration.usage)
            .filter((usage): usage is ComponentMemberStep => usage.kind === 'component-member');

        expect(componentMembers.length).toBeGreaterThan(0);
        componentMembers.forEach((usage) => {
            expect(usage.components.length).toBeGreaterThan(0);
            expect(usage.fix === 'manual' || Boolean(usage.replaceInScript)).toBe(true);
        });
    });

    it('keeps the deprecated JavaScript API calls report-only', () => {
        const memberCalls = migrations
            .flatMap((migration) => migration.usage)
            .filter((usage) => usage.kind === 'member-call');

        expect(memberCalls.length).toBeGreaterThan(0);
        memberCalls.forEach((usage) => {
            expect(usage.fix).toBe('manual');
        });
    });
});
