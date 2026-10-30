import { findMissingPublicNames } from '../private';

/**
 * @sw-package framework
 */

describe('public-component/private: findMissingPublicNames', () => {
    const component = {
        props: { value: {} },
        emits: ['update:value'],
        computed: { total: () => 0 },
        methods: { save: () => undefined },
        template: '{% block sw_example %}{% block sw_example_label %}{% endblock %}{% endblock %}',
    };

    it('finds nothing when every listed name exists', () => {
        // ARRANGE
        const api = {
            props: ['value'],
            emits: ['update:value'],
            computed: ['total'],
            methods: ['save'],
            blocks: ['sw_example', 'sw_example_label'],
        };

        // ACT
        const missing = findMissingPublicNames(api, component);

        // ASSERT
        expect(missing).toEqual([]);
    });

    it('names every listed name the component lacks', () => {
        // ARRANGE
        const api = { props: ['disabled'], methods: ['save', 'delete'], blocks: ['sw_example_footer'] };

        // ACT
        const missing = findMissingPublicNames(api, component);

        // ASSERT
        expect(missing).toEqual(['props:disabled', 'methods:delete', 'blocks:sw_example_footer']);
    });

    it('reads props and emits in array and object form', () => {
        // ARRANGE
        const api = { props: ['value'], emits: ['change'] };
        const arrayAndObjectComponent = { props: ['value'], emits: { change: null } };

        // ACT
        const missing = findMissingPublicNames(api, arrayAndObjectComponent);

        // ASSERT
        expect(missing).toEqual([]);
    });
});
