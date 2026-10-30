/**
 * @sw-package framework
 */
import ComponentFactory from 'src/core/factory/async-component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import { getBlockEntries, resetBlockIndex } from 'src/core/factory/twig-block-index';

describe('ComponentFactory Twig HMR registration', () => {
    beforeEach(() => {
        ComponentFactory.getComponentRegistry().clear();
        ComponentFactory.getOverrideRegistry().clear();
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.disableTwigCache();
        ComponentFactory.markComponentTemplatesAsNotResolved();
        resetBlockIndex();
    });

    it('declines an unknown override without changing the registered template', async () => {
        ComponentFactory.register('sw-hmr-known', { template: '<div>{% block content %}Base{% endblock %}</div>' });
        ComponentFactory.override('sw-hmr-known', {
            template: '{% block content %}Before{% endblock %}',
            __shopwareTwigHmrId: '/plugins/known.twig',
        });
        const before = await ComponentFactory.build('sw-hmr-known');
        expect(ComponentFactory.updateTemplateOverrideFromHmr('sw-hmr-missing', '/plugins/known.twig', 'Changed')).toBe(
            false,
        );
        expect(ComponentFactory.updateTemplateOverrideFromHmr('sw-hmr-known', '/plugins/unknown.twig', 'Changed')).toBe(
            false,
        );
        expect(await ComponentFactory.build('sw-hmr-known')).toEqual(before);
    });

    it('declines a registered override until its lazy template has been resolved', async () => {
        ComponentFactory.register('sw-hmr-pending', { template: '<div>{% block content %}Base{% endblock %}</div>' });
        ComponentFactory.override('sw-hmr-pending', {
            template: '{% block content %}Before{% endblock %}',
            __shopwareTwigHmrId: '/plugins/pending.twig',
        });
        expect(
            ComponentFactory.updateTemplateOverrideFromHmr(
                'sw-hmr-pending',
                '/plugins/pending.twig',
                '{% block content %}After{% endblock %}',
            ),
        ).toBe(false);
        expect(await ComponentFactory.build('sw-hmr-pending')).toHaveProperty('template', expect.stringContaining('Before'));
        expect(
            ComponentFactory.updateTemplateOverrideFromHmr(
                'sw-hmr-pending',
                '/plugins/pending.twig',
                '{% block content %}After{% endblock %}',
            ),
        ).toBe(true);
        expect(await ComponentFactory.build('sw-hmr-pending')).toHaveProperty('template', expect.stringContaining('After'));
        expect(ComponentFactory.getOverrideRegistry().get('sw-hmr-pending')).toHaveLength(1);
    });

    it('keeps unresolved template placeholders while rebuilding a changed override block index', async () => {
        ComponentFactory.register('sw-hmr-placeholder', { template: '<div>{% block content %}Base{% endblock %}</div>' });
        ComponentFactory.override('sw-hmr-placeholder', {
            template: '{% block content %}Before{% endblock %}',
            __shopwareTwigHmrId: '/plugins/placeholder.twig',
        });
        await ComponentFactory.build('sw-hmr-placeholder');
        TemplateFactory.registerTemplateOverride('sw-hmr-placeholder');
        expect(
            ComponentFactory.updateTemplateOverrideFromHmr(
                'sw-hmr-placeholder',
                '/plugins/placeholder.twig',
                '{% block content %}After{% endblock %}',
            ),
        ).toBe(true);
        expect(TemplateFactory.getTemplateOverrides('sw-hmr-placeholder')).toEqual(
            expect.arrayContaining([expect.objectContaining({ raw: null })]),
        );
        expect(getBlockEntries('sw-hmr-placeholder', 'content')).toHaveLength(1);
    });
});
