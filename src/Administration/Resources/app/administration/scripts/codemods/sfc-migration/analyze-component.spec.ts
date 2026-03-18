import path from 'path';
import fs from 'fs';
import { analyzeComponent, categorizeComponents, generateSummary } from './analyze-component';

const fixturesDir = path.join(__dirname, '__fixtures__');

function readFixture(name: string): string {
    return fs.readFileSync(path.join(fixturesDir, name), 'utf8');
}

/**
 * Integrative tests for the analysis pipeline.
 *
 * Tests analyze all four real fixture components as a batch, verifying the
 * complete per-component analysis, the full categorization, and the final
 * human-readable summary — not individual detection functions in isolation.
 */
describe('scripts/codemods/sfc-migration/analyze-component', () => {
    describe('per-component analysis of all fixtures', () => {
        it('marks simple-component as fully-migratable — uses only supported Options API features', () => {
            const result = analyzeComponent('sw-simple-card', readFixture('simple-component.index.js'));

            expect(result.componentName).toBe('sw-simple-card');
            expect(result.status).toBe('fully-migratable');
            expect(result.blockers).toEqual([]);
        });

        it('marks block-component as fully-migratable — watch and lifecycle hooks are supported', () => {
            const result = analyzeComponent('sw-block-card', readFixture('block-component.index.js'));

            expect(result.componentName).toBe('sw-block-card');
            expect(result.status).toBe('fully-migratable');
            expect(result.blockers).toEqual([]);
        });

        it('marks mixin-component as partially-migratable — mixins are a soft blocker', () => {
            const result = analyzeComponent('sw-mixin-list', readFixture('mixin-component.index.js'));

            expect(result.componentName).toBe('sw-mixin-list');
            expect(result.status).toBe('partially-migratable');
            expect(result.blockers).toContain('mixins');
        });

        it('marks render-component as not-migratable — render() is a hard blocker', () => {
            const result = analyzeComponent('sw-render-component', readFixture('render-component.index.js'));

            expect(result.componentName).toBe('sw-render-component');
            expect(result.status).toBe('not-migratable');
            expect(result.blockers).toContain('render function');
        });
    });

    describe('categorization of the full fixture batch', () => {
        let categories: ReturnType<typeof categorizeComponents>;

        beforeAll(() => {
            const analyses = [
                analyzeComponent('sw-simple-card', readFixture('simple-component.index.js')),
                analyzeComponent('sw-block-card', readFixture('block-component.index.js')),
                analyzeComponent('sw-mixin-list', readFixture('mixin-component.index.js')),
                analyzeComponent('sw-render-component', readFixture('render-component.index.js')),
            ];
            categories = categorizeComponents(analyses);
        });

        it('places the two fully-migratable fixtures in fullyMigratable', () => {
            expect(categories.fullyMigratable).toHaveLength(2);
            const names = categories.fullyMigratable.map((c) => c.componentName);
            expect(names).toContain('sw-simple-card');
            expect(names).toContain('sw-block-card');
        });

        it('places the mixin fixture in partiallyMigratable', () => {
            expect(categories.partiallyMigratable).toHaveLength(1);
            expect(categories.partiallyMigratable[0].componentName).toBe('sw-mixin-list');
            expect(categories.partiallyMigratable[0].blockers).toContain('mixins');
        });

        it('places the render fixture in notMigratable', () => {
            expect(categories.notMigratable).toHaveLength(1);
            expect(categories.notMigratable[0].componentName).toBe('sw-render-component');
            expect(categories.notMigratable[0].blockers).toContain('render function');
        });

        it('accounts for all 4 fixtures across the three categories with no component lost', () => {
            const total =
                categories.fullyMigratable.length +
                categories.partiallyMigratable.length +
                categories.notMigratable.length;
            expect(total).toBe(4);
        });
    });

    describe('summary report for the full fixture batch', () => {
        let summary: string;

        beforeAll(() => {
            const analyses = [
                analyzeComponent('sw-simple-card', readFixture('simple-component.index.js')),
                analyzeComponent('sw-block-card', readFixture('block-component.index.js')),
                analyzeComponent('sw-mixin-list', readFixture('mixin-component.index.js')),
                analyzeComponent('sw-render-component', readFixture('render-component.index.js')),
            ];
            summary = generateSummary(categorizeComponents(analyses));
        });

        it('mentions all four component names', () => {
            expect(summary).toContain('sw-simple-card');
            expect(summary).toContain('sw-block-card');
            expect(summary).toContain('sw-mixin-list');
            expect(summary).toContain('sw-render-component');
        });

        it('reports the correct counts for each migration category', () => {
            expect(summary).toMatch(/fully.migrat\w+.*2|2.*fully.migrat/i);
            expect(summary).toMatch(/partially.migrat\w+.*1|1.*partially.migrat/i);
            expect(summary).toMatch(/not.migrat\w+.*1|1.*not.migrat/i);
        });

        it('includes the blocker reason for the partially-migratable component', () => {
            expect(summary).toContain('mixins');
        });

        it('includes the blocker reason for the not-migratable component', () => {
            expect(summary).toContain('render function');
        });

        it('matches the complete summary output snapshot', () => {
            expect(summary).toMatchSnapshot();
        });
    });
});
