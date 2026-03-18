import path from 'path';
import fs from 'fs';
import { generateSFC, mergeComponentFiles } from './generate-sfc';

const fixturesDir = path.join(__dirname, '__fixtures__');

describe('scripts/codemods/sfc-migration/generate-sfc', () => {
    describe('generateSFC', () => {
        it('produces a <script setup> block when scriptType is "setup"', () => {
            const result = generateSFC({
                template: '<div>Hello</div>',
                script: 'const msg = ref("hi");',
                scriptType: 'setup',
            });

            expect(result).toContain('<script setup>');
            expect(result).not.toContain('<script>');
        });

        it('produces a <script> block (without setup) when scriptType is "options"', () => {
            const result = generateSFC({
                template: '<div>Hello</div>',
                script: 'export default { data() { return {}; } }',
                scriptType: 'options',
            });

            expect(result).toContain('<script>');
            expect(result).not.toContain('<script setup>');
        });

        it('wraps the template string in a <template> tag', () => {
            const result = generateSFC({
                template: '<div class="root">content</div>',
                script: '',
                scriptType: 'setup',
            });

            expect(result).toContain('<template>');
            expect(result).toContain('</template>');
            expect(result).toContain('<div class="root">content</div>');
        });

        it('omits the <template> block when template is empty', () => {
            const result = generateSFC({
                template: '',
                script: 'const x = ref(1);',
                scriptType: 'setup',
            });

            expect(result).not.toContain('<template>');
            expect(result).toContain('<script setup>');
        });

        it('places <template> before <script> in the output', () => {
            const result = generateSFC({
                template: '<div/>',
                script: 'const x = 1;',
                scriptType: 'setup',
            });

            const templatePos = result.indexOf('<template>');
            const scriptPos = result.indexOf('<script');
            expect(templatePos).toBeLessThan(scriptPos);
        });

        it('separates <template> and <script> with a blank line', () => {
            const result = generateSFC({
                template: '<div/>',
                script: 'const x = 1;',
                scriptType: 'setup',
            });

            expect(result).toMatch(/\n\n/);
        });

        it('closes the script block with </script>', () => {
            const result = generateSFC({
                template: '<div/>',
                script: 'const x = 1;',
                scriptType: 'setup',
            });

            expect(result).toContain('</script>');
        });
    });

    describe('mergeComponentFiles', () => {
        it('merges a simple component into a fully-migrated .vue SFC with <script setup>', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.html.twig'),
                'utf8',
            );
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.index.js'),
                'utf8',
            );

            const { sfc, status } = mergeComponentFiles(twigContent, jsContent);

            expect(status).toBe('fully-migrated');
            expect(sfc).toContain('<template>');
            expect(sfc).toContain('<script setup>');
            expect(sfc).not.toContain('<script>');
        });

        it('transforms twig blocks to <sw-block> in the merged template section', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'block-component.html.twig'),
                'utf8',
            );
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'block-component.index.js'),
                'utf8',
            );

            const { sfc, status } = mergeComponentFiles(twigContent, jsContent);

            expect(status).toBe('fully-migrated');
            expect(sfc).toContain('<sw-block');
            expect(sfc).not.toContain('{% block');
            expect(sfc).not.toContain('{% endblock %}');
        });

        it('falls back to Options API <script> for components using mixins', () => {
            const twigContent = '<div>list</div>';
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'mixin-component.index.js'),
                'utf8',
            );

            const { sfc, status } = mergeComponentFiles(twigContent, jsContent);

            expect(status).toBe('partially-migrated');
            expect(sfc).toContain('<script>');
            expect(sfc).not.toContain('<script setup>');
        });

        it('returns status not-migratable for components with a render() function', () => {
            const twigContent = '';
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'render-component.index.js'),
                'utf8',
            );

            const { sfc, status, blockers } = mergeComponentFiles(twigContent, jsContent);

            expect(status).toBe('not-migratable');
            expect(blockers).toContain('render function');
            expect(sfc).toBe('');
        });

        it('preserves the Shopware component registration name in the script output', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.html.twig'),
                'utf8',
            );
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.index.js'),
                'utf8',
            );

            const { sfc } = mergeComponentFiles(twigContent, jsContent);

            expect(sfc).toContain('sw-simple-card');
        });

        it('includes Composition API imports in the <script setup> block', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.html.twig'),
                'utf8',
            );
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.index.js'),
                'utf8',
            );

            const { sfc } = mergeComponentFiles(twigContent, jsContent);

            expect(sfc).toMatch(/import\s*\{[^}]*ref[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('includes inject() calls for injected services in the <script setup> block', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.html.twig'),
                'utf8',
            );
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.index.js'),
                'utf8',
            );

            const { sfc } = mergeComponentFiles(twigContent, jsContent);

            expect(sfc).toContain("inject('repositoryFactory')");
        });

        it('converts data() properties to ref() declarations in <script setup>', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.html.twig'),
                'utf8',
            );
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.index.js'),
                'utf8',
            );

            const { sfc } = mergeComponentFiles(twigContent, jsContent);

            expect(sfc).toContain('ref(');
        });

        it('converts computed properties to computed() declarations in <script setup>', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.html.twig'),
                'utf8',
            );
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.index.js'),
                'utf8',
            );

            const { sfc } = mergeComponentFiles(twigContent, jsContent);

            expect(sfc).toContain('computed(');
        });

        it('converts lifecycle hooks to Composition API equivalents in <script setup>', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'block-component.html.twig'),
                'utf8',
            );
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'block-component.index.js'),
                'utf8',
            );

            const { sfc } = mergeComponentFiles(twigContent, jsContent);

            expect(sfc).toContain('onMounted(');
        });

        it('matches the fully-migrated simple-component SFC output snapshot', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.html.twig'),
                'utf8',
            );
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.index.js'),
                'utf8',
            );

            const { sfc } = mergeComponentFiles(twigContent, jsContent);
            expect(sfc).toMatchSnapshot();
        });

        it('matches the fully-migrated block-component SFC output snapshot', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'block-component.html.twig'),
                'utf8',
            );
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'block-component.index.js'),
                'utf8',
            );

            const { sfc } = mergeComponentFiles(twigContent, jsContent);
            expect(sfc).toMatchSnapshot();
        });

        it('matches the partially-migrated mixin-component SFC output snapshot', () => {
            const twigContent = '<div>list</div>';
            const jsContent = fs.readFileSync(
                path.join(fixturesDir, 'mixin-component.index.js'),
                'utf8',
            );

            const { sfc } = mergeComponentFiles(twigContent, jsContent);
            expect(sfc).toMatchSnapshot();
        });
    });
});
