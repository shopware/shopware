/**
 * @sw-package framework
 */

import TemplateFactory from 'src/core/factory/template.factory';
import { registerNativeExtensionTargets } from 'src/core/factory/native-extension-targets';

describe('core/factory/template.factory.js - native block extension points', () => {
    beforeEach(() => {
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.disableTwigCache();
    });

    it('wraps only registered target blocks, around the content the Twig overrides produced', () => {
        registerNativeExtensionTargets({ component: 'tf-target', blocks: ['tf_target_block'] });

        TemplateFactory.registerComponentTemplate(
            'tf-target',
            '<div>{% block tf_target_block %}<p>base</p>{% endblock %}{% block tf_other_block %}<span>other</span>{% endblock %}</div>',
        );
        TemplateFactory.registerTemplateOverride(
            'tf-target',
            '{% block tf_target_block %}<p>from twig override</p>{% endblock %}',
        );

        TemplateFactory.resolveTemplates();

        expect(TemplateFactory.getNormalizedTemplateRegistry().get('tf-target').html).toBe(
            '<div><sw-block name="tf_target_block" :data="$dataScope" :legacy-shim="false"><p>from twig override</p></sw-block><span>other</span></div>',
        );
    });

    it('wraps a block once even when its component is normalized twice through an extension chain', () => {
        registerNativeExtensionTargets({ component: 'tf-base', blocks: ['tf_base_block'] });

        TemplateFactory.registerComponentTemplate(
            'tf-base',
            '<div>{% block tf_base_block %}<p>base</p>{% endblock %}</div>',
        );
        TemplateFactory.registerTemplateOverride('tf-base', '{% block tf_base_block %}<p>overridden</p>{% endblock %}');
        TemplateFactory.extendComponentTemplate('tf-child', 'tf-base');

        TemplateFactory.resolveTemplates();

        const html = TemplateFactory.getNormalizedTemplateRegistry().get('tf-base').html;

        expect(html.match(/<sw-block/g)).toHaveLength(1);
    });
});
