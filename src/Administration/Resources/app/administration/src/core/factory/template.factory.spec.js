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

    it('wraps a block once when an extending component overrides it', () => {
        registerNativeExtensionTargets({ component: 'tf-parent', blocks: ['tf_parent_block'] });

        TemplateFactory.registerComponentTemplate(
            'tf-parent',
            '<div>{% block tf_parent_block %}<p>parent</p>{% endblock %}</div>',
        );
        TemplateFactory.extendComponentTemplate(
            'tf-kid',
            'tf-parent',
            '{% block tf_parent_block %}<i>kid</i>{% parent %}{% endblock %}',
        );

        TemplateFactory.resolveTemplates();

        const registry = TemplateFactory.getNormalizedTemplateRegistry();

        // The child inherits the parent's tokens. A wrapper persisted on those tokens would be
        // inherited and then wrapped a second time.
        expect(registry.get('tf-parent').html).toBe(
            '<div><sw-block name="tf_parent_block" :data="$dataScope" :legacy-shim="false"><p>parent</p></sw-block></div>',
        );
        expect(registry.get('tf-kid').html).toBe(
            '<div><sw-block name="tf_parent_block" :data="$dataScope" :legacy-shim="false"><i>kid</i><p>parent</p></sw-block></div>',
        );
    });

    it('moves the extension point inside a block that is a single slot template', () => {
        registerNativeExtensionTargets({ component: 'tf-slot', blocks: ['tf_slot_block'] });

        TemplateFactory.registerComponentTemplate(
            'tf-slot',
            '<sw-card>{% block tf_slot_block %}<template #header="{ item }"><b>{{ item }}</b></template>{% endblock %}</sw-card>',
        );

        TemplateFactory.resolveTemplates();

        // Wrapping from the outside would bind #header to sw-block, which renders only its default
        // slot - the content would vanish from the DOM without any error.
        expect(TemplateFactory.getNormalizedTemplateRegistry().get('tf-slot').html).toBe(
            '<sw-card><template #header="{ item }"><sw-block name="tf_slot_block" :data="$dataScope" :legacy-shim="false"><b>{{ item }}</b></sw-block></template></sw-card>',
        );
    });

    it('leaves a block that mixes a slot template with other content unwrapped', () => {
        const warnSpy = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation();

        registerNativeExtensionTargets({ component: 'tf-mixed', blocks: ['tf_mixed_block'] });

        TemplateFactory.registerComponentTemplate(
            'tf-mixed',
            '<sw-card>{% block tf_mixed_block %}<template #header><b>h</b></template><p>extra</p>{% endblock %}</sw-card>',
        );

        TemplateFactory.resolveTemplates();

        // There is no single position that would serve both the slot and the trailing content.
        expect(TemplateFactory.getNormalizedTemplateRegistry().get('tf-mixed').html).toBe(
            '<sw-card><template #header><b>h</b></template><p>extra</p></sw-card>',
        );
        expect(warnSpy).toHaveBeenCalledWith('TemplateFactory', expect.stringContaining('tf_mixed_block'));

        warnSpy.mockRestore();
    });
});
