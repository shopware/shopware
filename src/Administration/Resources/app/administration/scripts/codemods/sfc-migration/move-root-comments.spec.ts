/**
 * @sw-package framework
 */

import { moveRootTwigCommentsOutOfTemplate, TWIG_COMMENT_MARKER } from './move-root-comments';

const twigComment = (body: string): string => `<!--${TWIG_COMMENT_MARKER}${body}-->`;

describe('move-root-comments', () => {
    it('moves a leading root Twig comment outside the template', () => {
        expect(
            moveRootTwigCommentsOutOfTemplate(
                `${twigComment(' @deprecated tag:v6.8.0 ')}\n<sw-block name="a"><div>content</div></sw-block>`,
            ),
        ).toEqual({
            template: '\n<sw-block name="a"><div>content</div></sw-block>',
            sfcComments: ['<!-- @deprecated tag:v6.8.0 -->'],
        });
    });

    it('moves a trailing root Twig comment outside the template', () => {
        expect(
            moveRootTwigCommentsOutOfTemplate(`<sw-block name="a"><div>content</div></sw-block>\n${twigComment(' note ')}`),
        ).toEqual({
            template: '<sw-block name="a"><div>content</div></sw-block>\n',
            sfcComments: ['<!-- note -->'],
        });
    });

    it('keeps several root Twig comments in source order', () => {
        expect(
            moveRootTwigCommentsOutOfTemplate(`${twigComment(' one ')}\n<div>content</div>\n${twigComment(' two ')}`),
        ).toEqual({
            template: '\n<div>content</div>\n',
            sfcComments: [
                '<!-- one -->',
                '<!-- two -->',
            ],
        });
    });

    it.each([
        '<div>content</div>',
        '<some-component />',
    ])('keeps a non-block root single-rooted: %s', (root) => {
        expect(moveRootTwigCommentsOutOfTemplate(`${twigComment(' note ')}\n${root}`)).toEqual({
            template: `\n${root}`,
            sfcComments: ['<!-- note -->'],
        });
    });

    it('keeps nested Twig comments in the template without their provenance marker', () => {
        expect(
            moveRootTwigCommentsOutOfTemplate(`<div>${twigComment(' one ')}<span />${twigComment(' two ')}</div>`),
        ).toEqual({
            template: '<div><!-- one --><span /><!-- two --></div>',
            sfcComments: [],
        });
    });

    it('leaves authored HTML comments where they were', () => {
        const template = '<!-- eslint-disable-next-line vue/no-v-html -->\n<div>content</div>';

        expect(moveRootTwigCommentsOutOfTemplate(template)).toEqual({ template, sfcComments: [] });
    });

    it('only removes the marker when Vue cannot parse the template', () => {
        expect(moveRootTwigCommentsOutOfTemplate(`${twigComment(' note ')}<div>`)).toEqual({
            template: '<!-- note --><div>',
            sfcComments: [],
        });
    });
});
