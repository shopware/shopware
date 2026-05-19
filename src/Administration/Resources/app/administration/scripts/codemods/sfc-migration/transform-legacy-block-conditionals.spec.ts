import transformLegacyBlockConditionals from './transform-legacy-block-conditionals';

describe('scripts/codemods/sfc-migration/transform-legacy-block-conditionals', () => {
    it('rewrites trailing core block v-if chains to legacy block helpers', () => {
        const result = transformLegacyBlockConditionals(`
<sw-block name="sw_example" :data="$dataScope">
    <div v-if="showPrimary">primary</div>
    <div v-else-if="showSecondary">secondary</div>
</sw-block>
        `);

        expect(result).toContain('v-if="$swLegacyBlockIf(\'sw_example\', showPrimary)"');
        expect(result).toContain('v-if="$swLegacyBlockElseIf(\'sw_example\', showSecondary)"');
        expect(result).not.toContain('v-else-if="showSecondary"');
    });

    it('rewrites sw-block-parent followed by v-else to the legacy else helper', () => {
        const result = transformLegacyBlockConditionals(`
<sw-block name="sw_example" :data="$dataScope">
    <sw-block-parent/>
    <div v-else class="fallback">fallback</div>
</sw-block>
        `);

        expect(result).toContain('<sw-block-parent/>');
        expect(result).toContain('v-if="$swLegacyBlockElse(\'sw_example\')"');
        expect(result).not.toContain('v-else class="fallback"');
    });

    it('rewrites sw-block-parent followed by v-else-if to the legacy else-if helper', () => {
        const result = transformLegacyBlockConditionals(`
<sw-block name="sw_example" :data="$dataScope">
    <sw-block-parent/>
    <div v-else-if="showOverride">override</div>
</sw-block>
        `);

        expect(result).toContain('v-if="$swLegacyBlockElseIf(\'sw_example\', showOverride)"');
        expect(result).not.toContain('v-else-if="showOverride"');
    });

    it('does not rewrite leading v-else branches without sw-block-parent', () => {
        const result = transformLegacyBlockConditionals(`
<sw-block name="sw_example" :data="$dataScope">
    <div v-else class="fallback">fallback</div>
</sw-block>
        `);

        expect(result).toContain('v-else class="fallback"');
        expect(result).not.toContain('$swLegacyBlockElse');
    });

    it('escapes rewritten helper expressions for double-quoted attributes', () => {
        const result = transformLegacyBlockConditionals(`
<sw-block name="sw_example" :data="$dataScope">
    <div v-if="label === 'primary' && value === &quot;visible&quot;">primary</div>
</sw-block>
        `);

        expect(result).toContain(
            "v-if=\"$swLegacyBlockIf('sw_example', label === 'primary' &amp;&amp; value === &quot;visible&quot;)\"",
        );
    });

    it('leaves unrelated templates untouched', () => {
        const template = '<div v-if="showPrimary">primary</div>';

        expect(transformLegacyBlockConditionals(template)).toBe(template);
    });
});
