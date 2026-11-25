/**
 * @sw-package framework
 *
 * Runs the break check on two commits of a repository.
 */
import type { Model } from '../model/model';
import { buildModel } from '../read/build-model';
import { readSourceTreeAt, resolveCommit } from '../source/git-source-tree';
import type { GitRepository } from '../source/git-source-tree';
import { classifyChanges } from './break-check';
import type { Finding } from './finding';

/**
 * @private
 *
 * Both commits are read with the generator and the major flags of this checkout, so the two models only differ
 * where the sources do. The check prints no snapshot, so a template that no component uses does not stop it.
 */
export function compareCommits(
    repository: GitRepository,
    majorFlags: readonly string[],
    base: string,
    head: string,
): Finding[] {
    const modelAt = (revision: string): Model =>
        buildModel(readSourceTreeAt(repository, resolveCommit(repository, revision)), { majorFlags });

    return classifyChanges(modelAt(base), modelAt(head));
}
