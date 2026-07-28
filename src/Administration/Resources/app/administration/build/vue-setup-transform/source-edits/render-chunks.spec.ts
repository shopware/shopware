/**
 * @sw-package framework
 *
 * Focused coverage for renderIndent's protected-range handling. A multi-line template literal must keep
 * its continuation lines at their original column - indenting them would rewrite the runtime string.
 * The full-SFC suite only observes this indirectly, so pin it directly.
 */

import { fromSource, indent } from './chunks';
import { render } from './render-chunks';

describe('build/vue-setup-transform source-edits/render-chunks', () => {
    it('indents an ordinary continuation line but leaves a protected one untouched', () => {
        const source = 'const x = `line1\nline2`;';
        const block = { contentStart: 0, content: source };
        const chunk = indent([fromSource(block, { start: 0, end: source.length })], 4);

        const litStart = source.indexOf('`');
        const litEnd = source.lastIndexOf('`') + 1;

        // Without the protected range, every line gets the 4-space indent.
        expect(render([chunk], source, 0, [])).toBe('    const x = `line1\n    line2`;');

        // With the literal marked protected, its continuation line keeps column 0.
        expect(
            render([chunk], source, 0, [
                [
                    litStart,
                    litEnd,
                ],
            ]),
        ).toBe('    const x = `line1\nline2`;');
    });
});
