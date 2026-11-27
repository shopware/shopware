/**
 * @sw-package framework
 *
 * Runs the break check on two commits of a repository.
 */
import { readSourceTreeAt, resolveCommit } from '../source/git-source-tree';
import type { GitRepository } from '../source/git-source-tree';
import type { SourceTree } from '../source/source-tree';
import { compareSourceTrees } from './break-check';
import type { Finding } from './finding';

/**
 * @private
 *
 * Both commits are read with the generator and the major flags of this checkout. The check prints no snapshot,
 * so a template that no component uses does not stop it.
 */
export function compareCommits(
    repository: GitRepository,
    majorFlags: readonly string[],
    base: string,
    head: string,
): Finding[] {
    const treeAt = (revision: string): SourceTree => readSourceTreeAt(repository, resolveCommit(repository, revision));

    return compareSourceTrees(treeAt(base), treeAt(head), majorFlags);
}
