/**
 * @sw-package framework
 */

const { SourceMapConsumer } = require('source-map-js');
const transformer = require('./shopwareSetupVueTransformer');

const overrideSource = `<template>
    <sw-block extends="sw_card">
        <p>{{ headline }}</p>
    </sw-block>
</template>
<script setup>
import { computed } from 'vue';

const previousState = useSwPreviousState();

const headline = computed(() => \`\${previousState.headline.value} and the override\`);

swDefineOverride({
    headline,
});
</script>
`;

const transformOptions = {
    config: { cwd: process.cwd(), rootDir: process.cwd() },
    configString: '{}',
    instrument: false,
    rootDir: process.cwd(),
};

function positionOf(code, marker) {
    const lines = code.slice(0, code.indexOf(marker)).split('\n');

    return { line: lines.length, column: lines[lines.length - 1].length };
}

describe('test/transformer/shopwareSetupVueTransformer', () => {
    beforeAll(() => {
        global.allowedErrors.push({ method: 'warn', msg: 'Browserslist: browsers data' });
    });

    it('maps compiled override code back to the authored line', () => {
        const filename = '/administration/src/sw-map-probe.override.vue';
        const { code, map } = transformer.process(overrideSource, filename, transformOptions);

        const mapped = new SourceMapConsumer(map).originalPositionFor(positionOf(code, 'and the override'));

        expect(mapped.source).toBe(filename);
        expect(mapped.line).toBe(positionOf(overrideSource, 'and the override').line);
    });

    it('computes the cache key without running the transform', () => {
        // The transform rejects this file, so a key for it proves the transform did not run.
        const invalidSource = '<script>export default {};</script>';

        expect(() =>
            transformer.getCacheKey(invalidSource, '/administration/src/sw-invalid.vue', transformOptions),
        ).not.toThrow();
    });

    it('keys the cache on the raw source', () => {
        const filename = '/administration/src/sw-map-probe.override.vue';
        const key = transformer.getCacheKey(overrideSource, filename, transformOptions);

        expect(transformer.getCacheKey(overrideSource, filename, transformOptions)).toBe(key);
        expect(transformer.getCacheKey(`${overrideSource}\n`, filename, transformOptions)).not.toBe(key);
    });

    it('compiles dependency SFCs without the Shopware setup transform', () => {
        const source = '<script>export default { name: "DependencyWidget" };</script>';

        const { code } = transformer.process(
            source,
            '/administration/node_modules/some-package/Widget.vue',
            transformOptions,
        );

        expect(code).toContain('DependencyWidget');
    });
});
