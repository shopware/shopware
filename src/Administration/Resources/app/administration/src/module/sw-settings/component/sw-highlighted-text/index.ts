/**
 * @sw-package framework
 */
import template from './sw-highlighted-text.html.twig';
import './sw-highlighted-text.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        label: {
            required: true,
            type: String,
        },
        highlights: {
            // { startIndex: number, endIndex: number }[]
            required: false,
            type: Array as () => { startIndex: number; endIndex: number }[] | null,
            default: null,
        },
    },

    computed: {
        styledLabels(): { text: string; highlight: boolean }[] {
            // idea is to iterate over styledLabels and render <span class="highlight"> for parts where highlight is true
            if (!this.highlights || this.highlights.length === 0) {
                return [{ text: this.label, highlight: false }];
            }
            const highlights = this.highlights.sort((a, b) => a.startIndex - b.startIndex);
            let i = 0;
            const styledLabels = [];
            for (const highlight of highlights) {
                if (i < highlight.startIndex) {
                    styledLabels.push({
                        text: this.label.substring(i, highlight.startIndex),
                        highlight: false,
                    });
                }
                styledLabels.push({
                    text: this.label.substring(highlight.startIndex, highlight.endIndex),
                    highlight: true,
                });
                i = highlight.endIndex;
            }
            if (i < this.label.length) {
                styledLabels.push({
                    text: this.label.substring(i),
                    highlight: false,
                });
            }
            return styledLabels;
        },
    },
});
