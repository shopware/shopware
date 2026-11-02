import { extractComponentApi } from './extract-component-api';

describe('extractComponentApi', () => {
    it('reads the public members of an Options API component and its template', () => {
        // ARRANGE
        const component = `
            export default {
                template,
                emits: ['update:value', 'delete'],
                props: { value: { type: String }, disabled: Boolean },
                computed: { label() { return ''; }, ...mapState(useStore, ['items']) },
                methods: { save() {}, 'on-click': () => {} },
            };
        `;
        const template = `
            {% block sw_example %}
                <slot name="header"></slot>
                {%- block sw_example_content %}<slot />{% endblock %}
                {% if alternative %}{% block sw_example_content %}{% endblock %}{% endif %}
            {% endblock %}
        `;

        // ACT
        const api = extractComponentApi(component, template);

        // ASSERT
        expect(api).toEqual({
            props: ['value', 'disabled'],
            emits: ['update:value', 'delete'],
            slots: ['header', 'default'],
            blocks: ['sw_example', 'sw_example_content'],
            computed: ['label'],
            methods: ['save', 'on-click'],
            unresolved: ["computed: ...mapState(useStore, ['items'])"],
        });
    });

    it('reads a component wrapped in wrapComponentConfig', () => {
        // ACT
        const api = extractComponentApi("export default Shopware.Component.wrapComponentConfig({ props: ['value'] });", '');

        // ASSERT
        expect(api.props).toEqual(['value']);
    });

    it('reports a file without a component as unresolved', () => {
        // ACT
        const api = extractComponentApi('export const helper = 1;', '');

        // ASSERT
        expect(api.unresolved).toEqual(['the component: no `export default {}` found']);
    });
});
