/**
 * @sw-package framework
 */
import ComponentFactory from 'src/core/factory/async-component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import { definePublicComponent } from 'src/core/public-component';

describe('core/factory/async-component.factory.ts: components declared with definePublicComponent()', () => {
    beforeEach(() => {
        ComponentFactory.getComponentRegistry().clear();
        TemplateFactory.getTemplateRegistry().clear();
    });

    it('registers the component under its declared name and loads it lazily', async () => {
        const load = jest.fn(() =>
            Promise.resolve({ default: { template: '<div>{% block sw_example %}{% endblock %}</div>' } }),
        );

        ComponentFactory.register(definePublicComponent({ name: 'sw-example', load }));

        expect(load).not.toHaveBeenCalled();
        expect(await ComponentFactory.getComponentRegistry().get('sw-example')()).toEqual(
            expect.objectContaining({ name: 'sw-example' }),
        );
        expect(load).toHaveBeenCalledTimes(1);
    });
});
