function createState() {
    return { open: false };
}

/**
 * @private
 */
export default {
    template: `
        {% block sw_fixture_private %}
            <div><slot name="content"></slot></div>
        {% endblock %}
    `,

    props: ['label', 'value'],

    data() {
        return createState();
    },
};
