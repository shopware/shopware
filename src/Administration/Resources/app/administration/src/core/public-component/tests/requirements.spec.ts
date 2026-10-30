import { definePublicComponent } from '../index';

/**
 * @sw-package framework
 */

describe('public-component', () => {
    /**
     * @requirement A component's name, loader and public API are declared together and stay as written.
     * The component factory and the meta tests read the declaration, so it must not change on the way.
     */
    it('keeps the declaration as written', () => {
        // ARRANGE
        const declaration = {
            name: 'sw-example',
            load: () => Promise.resolve({ props: { value: String }, template: '{% block sw_example %}{% endblock %}' }),
            public: { props: ['value'], blocks: ['sw_example'] },
        } as const;

        // ACT
        const declared = definePublicComponent(declaration);

        // ASSERT
        expect(declared).toBe(declaration);
    });
});
