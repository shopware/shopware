import template from './sw-shortcut-overview-item.html.twig';

export default {
    name: 'sw-shortcut-overview-item',
    template,

    props: {
        title: {
            type: String,
            required: true
        },
        content: {
            type: String,
            required: true
        }
    },

    computed: {
        keys() {
            const letters = this.content.split('') || [];

            return letters.reduce((accumulator, letter) => {
                if (letter === ' ') {
                    return `${accumulator} `;
                }

                return `${accumulator}<span>${letter}</span>`;
            }, '');
        }
    }
};
