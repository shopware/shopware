/**
 * @sw-package framework
 */

/**
 * A Twig comment rendered no node, so keeping one at the Vue template root would turn a component
 * with one root into a development-only fragment. Root Twig comments are moved outside `<template>`
 * as SFC comments instead: the note stays in the generated source without entering the render tree.
 *
 * The marker preserves provenance while the other template passes run. An authored HTML comment is
 * left exactly where it was, because it already rendered as a comment before the migration. Markers
 * on nested Twig comments are only removed; those comments cannot change the component's root shape.
 */

import { NodeTypes } from '@vue/compiler-dom';
import type { CommentNode } from '@vue/compiler-dom';
import MagicString from 'magic-string';
import { parseTemplate } from './template-ast';

const TWIG_COMMENT_MARKER = '__sfc_migration_twig_comment__';
const MARKED_COMMENT_START = `<!--${TWIG_COMMENT_MARKER}`;

type RootCommentsResult = {
    template: string;
    sfcComments: string[];
};

function restoreTwigComment(comment: string): string {
    return comment.replaceAll(MARKED_COMMENT_START, '<!--');
}

function moveRootTwigCommentsOutOfTemplate(template: string): RootCommentsResult {
    const root = parseTemplate(template);

    if (root === null) {
        return { template: restoreTwigComment(template), sfcComments: [] };
    }

    const comments = root.children.filter(
        (child): child is CommentNode => child.type === NodeTypes.COMMENT && child.content.startsWith(TWIG_COMMENT_MARKER),
    );
    const output = new MagicString(template);

    for (const comment of comments) {
        output.remove(comment.loc.start.offset, comment.loc.end.offset);
    }

    return {
        template: restoreTwigComment(output.toString()),
        sfcComments: comments.map((comment) =>
            restoreTwigComment(template.slice(comment.loc.start.offset, comment.loc.end.offset)),
        ),
    };
}

export { moveRootTwigCommentsOutOfTemplate, TWIG_COMMENT_MARKER, type RootCommentsResult };
