import { parse as parseTemplate } from '@vue/compiler-dom';
import { collectTemplateReferences } from '../../../build/vue-setup-transform/template-analyzer/template-references';

/**
 * The free identifiers a Vue template reads (interpolations, directive
 * expressions, bindings), with v-for/v-slot scope names excluded. Reuses the
 * native-setup transform's template analyzer so scoping is handled the same way.
 *
 * The codemod needs this because a mixin member (e.g. `placeholder`) is often
 * called only in the template. Script-only usage detection would drop its
 * composable, leaving the template binding undefined.
 */
export function collectTemplateReferenceNames(template: string): Set<string> {
    const ast = parseTemplate(template);

    return collectTemplateReferences(ast.children, new Set<string>()).references;
}
