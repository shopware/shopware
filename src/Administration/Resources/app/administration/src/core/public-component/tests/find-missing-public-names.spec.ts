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
        expect(
            findMissingPublicNames(
                {
                    props: ['value'],
                    emits: ['update:value'],
                    computed: ['total'],
                    methods: ['save'],
                    blocks: ['sw_example', 'sw_example_label'],
                },
                component,
            ),
        ).toEqual([]);
    });

    it('names every listed name the component lacks', () => {
        expect(
            findMissingPublicNames(
                { props: ['disabled'], methods: ['save', 'delete'], blocks: ['sw_example_footer'] },
                component,
            ),
        ).toEqual(['props:disabled', 'methods:delete', 'blocks:sw_example_footer']);
    });

    it('reads props and emits in array and object form', () => {
        expect(
            findMissingPublicNames({ props: ['value'], emits: ['change'] }, { props: ['value'], emits: { change: null } }),
        ).toEqual([]);
    });
});
