/**
 * @sw-package framework
 */

import TemplateFactory from 'src/core/factory/template.factory';
import { getInspectedBlock, resetBlockInspector, setBlockInspectorEnabled } from 'src/core/factory/block-inspector';

function renderedHtml(componentName: string): string {
    TemplateFactory.resolveTemplates();

    return (TemplateFactory.getNormalizedTemplateRegistry().get(componentName) as { html: string }).html;
}

describe('core/factory/template.factory.js - block inspector markers', () => {
    beforeEach(() => {
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.disableTwigCache();
        resetBlockInspector();
        setBlockInspectorEnabled(true);
    });

    afterEach(() => {
        setBlockInspectorEnabled(false);
        resetBlockInspector();
    });

    it('renders templates untouched while the inspector is disabled', () => {
        setBlockInspectorEnabled(false);
        TemplateFactory.registerComponentTemplate('bi-off', '<div>{% block bi_off_block %}<p>x</p>{% endblock %}</div>');

        expect(renderedHtml('bi-off')).toBe('<div><p>x</p></div>');
        expect(getInspectedBlock('bi_off_block')).toBeUndefined();
    });

    it('marks the elements of every block, innermost name first', () => {
        TemplateFactory.registerComponentTemplate(
            'bi-nested',
            '{% block bi_outer %}<div>{% block bi_inner %}<p>x</p>{% endblock %}</div>{% endblock %}',
        );

        expect(renderedHtml('bi-nested')).toBe('<div data-sw-block="bi_outer"><p data-sw-block="bi_inner">x</p></div>');
    });

    it('collects the names of blocks that start on the same element', () => {
        TemplateFactory.registerComponentTemplate(
            'bi-same',
            '{% block bi_same_outer %}{% block bi_same_inner %}<p>x</p>{% endblock %}{% endblock %}',
        );

        expect(renderedHtml('bi-same')).toBe('<p data-sw-block="bi_same_inner bi_same_outer">x</p>');
    });

    it('marks the content a Twig override and parent produced', () => {
        TemplateFactory.registerComponentTemplate(
            'bi-override',
            '<div>{% block bi_override_block %}<p>base</p>{% endblock %}</div>',
        );
        TemplateFactory.registerTemplateOverride(
            'bi-override',
            '{% block bi_override_block %}{% parent %}<i>plugin</i>{% endblock %}',
        );

        expect(renderedHtml('bi-override')).toBe(
            '<div><p data-sw-block="bi_override_block">base</p><i data-sw-block="bi_override_block">plugin</i></div>',
        );
    });

    it('marks the blocks of an extending component and attributes them to the right components', () => {
        TemplateFactory.registerComponentTemplate(
            'bi-parent',
            '<div>{% block bi_parent_block %}<p>p</p>{% endblock %}</div>',
        );
        TemplateFactory.extendComponentTemplate(
            'bi-kid',
            'bi-parent',
            '{% block bi_parent_block %}<i>k</i>{% parent %}{% endblock %}',
        );

        TemplateFactory.resolveTemplates();

        const registry = TemplateFactory.getNormalizedTemplateRegistry() as Map<string, { html: string }>;

        expect(registry.get('bi-parent')?.html).toBe('<div><p data-sw-block="bi_parent_block">p</p></div>');
        expect(registry.get('bi-kid')?.html).toBe(
            '<div><i data-sw-block="bi_parent_block">k</i><p data-sw-block="bi_parent_block">p</p></div>',
        );
        expect(getInspectedBlock('bi_parent_block')).toEqual({
            name: 'bi_parent_block',
            component: 'bi-parent',
            kind: 'twig',
        });
    });

    it('marks the children of a slot template block', () => {
        TemplateFactory.registerComponentTemplate(
            'bi-slot',
            '<sw-card>{% block bi_slot_block %}<template #header><b>h</b></template>{% endblock %}</sw-card>',
        );

        expect(renderedHtml('bi-slot')).toBe(
            '<sw-card><template #header><b data-sw-block="bi_slot_block">h</b></template></sw-card>',
        );
    });
});
