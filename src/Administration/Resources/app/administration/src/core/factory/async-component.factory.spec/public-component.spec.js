/**
 * @sw-package framework
 */
import ComponentFactory from 'src/core/factory/async-component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import { definePublicComponent } from 'src/core/public-component';

describe('core/factory/async-component.factory.ts: components declared with definePublicComponent()', () => {
    const template = '<div>{% block sw_example %}{% endblock %}</div>';

    beforeEach(() => {
        ComponentFactory.getComponentRegistry().clear();
        TemplateFactory.getTemplateRegistry().clear();
    });

    it('registers the component under its declared name without loading it', () => {
        const load = jest.fn(() => Promise.resolve({ default: { template } }));

        ComponentFactory.register(definePublicComponent({ name: 'sw-example', load }));

        expect(ComponentFactory.getComponentRegistry().has('sw-example')).toBe(true);
        expect(load).not.toHaveBeenCalled();
    });

    it('loads the component when it is resolved', async () => {
        const load = jest.fn(() => Promise.resolve({ default: { template } }));
        ComponentFactory.register(definePublicComponent({ name: 'sw-example', load }));

        const component = await ComponentFactory.getComponentRegistry().get('sw-example')();

        expect(component).toEqual(expect.objectContaining({ name: 'sw-example' }));
        expect(load).toHaveBeenCalledTimes(1);
    });
});
