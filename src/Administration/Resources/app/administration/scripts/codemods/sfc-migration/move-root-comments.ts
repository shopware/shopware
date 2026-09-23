/**
 * @sw-package framework
 */

/**
 * A Twig comment rendered no node; at the template root it would make a development-only fragment,
 * so it moves outside `<template>`. The marker tells it apart from an authored HTML comment, which
 * already rendered and stays put.
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
