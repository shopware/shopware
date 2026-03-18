import path from 'path';
import fs from 'fs';
import { analyzeComponent, categorizeComponents, generateSummary } from './analyze-component';
import type { ComponentAnalysis, MigrationCategories } from './analyze-component';

const fixturesDir = path.join(__dirname, '__fixtures__');

describe('scripts/codemods/sfc-migration/analyze-component', () => {
    describe('analyzeComponent', () => {
        it('marks a component as fully-migratable when it uses only supported features', () => {
            const jsContent = `
Shopware.Component.register('sw-simple', {
    template,
    inject: ['acl'],
    data() {
        return { count: 0, label: 'Hello' };
    },
    computed: {
        doubled() { return this.count * 2; },
    },
    methods: {
        increment() { this.count++; },
    },
    mounted() {
        this.count = 1;
    },
});`;

            const result = analyzeComponent('sw-simple', jsContent);

            expect(result.status).toBe('fully-migratable');
            expect(result.blockers).toEqual([]);
            expect(result.componentName).toBe('sw-simple');
        });

        it('marks a component as partially-migratable when it uses mixins', () => {
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'mixin-component.index.js'),
                'utf8',
            );

            const result = analyzeComponent('sw-mixin-list', jsContent);

            expect(result.status).toBe('partially-migratable');
            expect(result.blockers).toContain('mixins');
        });

        it('marks a component as not-migratable when it has a render() function', () => {
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'render-component.index.js'),
                'utf8',
            );

            const result = analyzeComponent('sw-render-component', jsContent);

            expect(result.status).toBe('not-migratable');
            expect(result.blockers).toContain('render function');
        });

        it('marks a component as partially-migratable for Options API extends', () => {
            const jsContent = `
Shopware.Component.extend('sw-child', 'sw-parent', {
    template,
    data() { return { extra: true }; },
});`;

            const result = analyzeComponent('sw-child', jsContent);

            expect(result.status).toBe('partially-migratable');
            expect(result.blockers).toContain('extends');
        });

        it('lists all blockers when multiple unsupported features are used', () => {
            const jsContent = `
Shopware.Component.register('sw-complex', {
    template,
    mixins: [someMixin],
    render() { return h('div'); },
});`;

            const result = analyzeComponent('sw-complex', jsContent);

            expect(result.blockers).toContain('mixins');
            expect(result.blockers).toContain('render function');
        });

        it('includes the component name in the analysis result', () => {
            const jsContent = `Shopware.Component.register('sw-named', { template });`;

            const result = analyzeComponent('sw-named', jsContent);

            expect(result.componentName).toBe('sw-named');
        });

        it('correctly analyses the simple-component fixture as fully-migratable', () => {
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.index.js'),
                'utf8',
            );

            const result = analyzeComponent('sw-simple-card', jsContent);

            expect(result.status).toBe('fully-migratable');
            expect(result.blockers).toEqual([]);
        });

        it('correctly analyses the block-component fixture as fully-migratable', () => {
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'block-component.index.js'),
                'utf8',
            );

            const result = analyzeComponent('sw-block-card', jsContent);

            expect(result.status).toBe('fully-migratable');
            expect(result.blockers).toEqual([]);
        });
    });

    describe('categorizeComponents', () => {
        it('groups analyses by status into the three categories', () => {
            const analyses: ComponentAnalysis[] = [
                { componentName: 'sw-a', status: 'fully-migratable', blockers: [] },
                { componentName: 'sw-b', status: 'partially-migratable', blockers: ['mixins'] },
                { componentName: 'sw-c', status: 'not-migratable', blockers: ['render function'] },
            ];

            const result = categorizeComponents(analyses);

            expect(result.fullyMigratable).toHaveLength(1);
            expect(result.partiallyMigratable).toHaveLength(1);
            expect(result.notMigratable).toHaveLength(1);

            expect(result.fullyMigratable[0].componentName).toBe('sw-a');
            expect(result.partiallyMigratable[0].componentName).toBe('sw-b');
            expect(result.notMigratable[0].componentName).toBe('sw-c');
        });

        it('handles multiple fully-migratable components', () => {
            const analyses: ComponentAnalysis[] = [
                { componentName: 'sw-a', status: 'fully-migratable', blockers: [] },
                { componentName: 'sw-b', status: 'fully-migratable', blockers: [] },
            ];

            const result = categorizeComponents(analyses);

            expect(result.fullyMigratable).toHaveLength(2);
            expect(result.partiallyMigratable).toHaveLength(0);
            expect(result.notMigratable).toHaveLength(0);
        });

        it('returns empty arrays for all categories when input is empty', () => {
            const result = categorizeComponents([]);

            expect(result.fullyMigratable).toEqual([]);
            expect(result.partiallyMigratable).toEqual([]);
            expect(result.notMigratable).toEqual([]);
        });

        it('preserves all components of each category', () => {
            const analyses: ComponentAnalysis[] = [
                { componentName: 'sw-a', status: 'fully-migratable', blockers: [] },
                { componentName: 'sw-b', status: 'fully-migratable', blockers: [] },
                { componentName: 'sw-c', status: 'partially-migratable', blockers: ['mixins'] },
                { componentName: 'sw-d', status: 'not-migratable', blockers: ['render function'] },
                { componentName: 'sw-e', status: 'not-migratable', blockers: ['render function'] },
            ];

            const result = categorizeComponents(analyses);

            expect(result.fullyMigratable).toHaveLength(2);
            expect(result.partiallyMigratable).toHaveLength(1);
            expect(result.notMigratable).toHaveLength(2);
        });
    });

    describe('generateSummary', () => {
        it('includes the count of fully-migratable components', () => {
            const categories: MigrationCategories = {
                fullyMigratable: [{ componentName: 'sw-a', status: 'fully-migratable', blockers: [] }],
                partiallyMigratable: [],
                notMigratable: [],
            };

            const summary = generateSummary(categories);

            expect(summary).toContain('1');
            expect(summary).toMatch(/fully.migrat/i);
        });

        it('includes the count of partially-migratable components', () => {
            const categories: MigrationCategories = {
                fullyMigratable: [],
                partiallyMigratable: [{ componentName: 'sw-b', status: 'partially-migratable', blockers: ['mixins'] }],
                notMigratable: [],
            };

            const summary = generateSummary(categories);

            expect(summary).toMatch(/partially.migrat/i);
        });

        it('includes the count of not-migratable components', () => {
            const categories: MigrationCategories = {
                fullyMigratable: [],
                partiallyMigratable: [],
                notMigratable: [{ componentName: 'sw-c', status: 'not-migratable', blockers: ['render function'] }],
            };

            const summary = generateSummary(categories);

            expect(summary).toMatch(/not.migrat/i);
        });

        it('lists the component name for not-migratable entries', () => {
            const categories: MigrationCategories = {
                fullyMigratable: [],
                partiallyMigratable: [],
                notMigratable: [
                    { componentName: 'sw-render-comp', status: 'not-migratable', blockers: ['render function'] },
                ],
            };

            const summary = generateSummary(categories);

            expect(summary).toContain('sw-render-comp');
        });

        it('lists the blockers for not-migratable entries', () => {
            const categories: MigrationCategories = {
                fullyMigratable: [],
                partiallyMigratable: [],
                notMigratable: [
                    { componentName: 'sw-c', status: 'not-migratable', blockers: ['render function'] },
                ],
            };

            const summary = generateSummary(categories);

            expect(summary).toContain('render function');
        });

        it('lists the backoff strategy used for partially-migratable entries', () => {
            const categories: MigrationCategories = {
                fullyMigratable: [],
                partiallyMigratable: [
                    { componentName: 'sw-mix', status: 'partially-migratable', blockers: ['mixins'] },
                ],
                notMigratable: [],
            };

            const summary = generateSummary(categories);

            expect(summary).toContain('sw-mix');
            expect(summary).toContain('mixins');
        });

        it('produces a "No components found" message when all categories are empty', () => {
            const categories: MigrationCategories = {
                fullyMigratable: [],
                partiallyMigratable: [],
                notMigratable: [],
            };

            const summary = generateSummary(categories);

            expect(summary).toMatch(/no components found/i);
        });

        it('produces a summary covering all categories in one call', () => {
            const categories: MigrationCategories = {
                fullyMigratable: [
                    { componentName: 'sw-a', status: 'fully-migratable', blockers: [] },
                    { componentName: 'sw-b', status: 'fully-migratable', blockers: [] },
                ],
                partiallyMigratable: [
                    { componentName: 'sw-c', status: 'partially-migratable', blockers: ['mixins'] },
                ],
                notMigratable: [
                    { componentName: 'sw-d', status: 'not-migratable', blockers: ['render function'] },
                ],
            };

            const summary = generateSummary(categories);

            expect(summary).toMatch(/fully.migrat/i);
            expect(summary).toMatch(/partially.migrat/i);
            expect(summary).toMatch(/not.migrat/i);
            expect(summary).toContain('sw-a');
            expect(summary).toContain('sw-b');
            expect(summary).toContain('sw-c');
            expect(summary).toContain('sw-d');
        });

        it('matches the summary output snapshot for a mixed set of components', () => {
            const categories: MigrationCategories = {
                fullyMigratable: [
                    { componentName: 'sw-simple-card', status: 'fully-migratable', blockers: [] },
                    { componentName: 'sw-block-card', status: 'fully-migratable', blockers: [] },
                ],
                partiallyMigratable: [
                    { componentName: 'sw-mixin-list', status: 'partially-migratable', blockers: ['mixins'] },
                ],
                notMigratable: [
                    {
                        componentName: 'sw-render-component',
                        status: 'not-migratable',
                        blockers: ['render function'],
                    },
                ],
            };

            expect(generateSummary(categories)).toMatchSnapshot();
        });
    });
});
