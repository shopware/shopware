/**
 * @sw-package inventory
 */

/**
 * Parses the raw `matched_queries` map (clause-name JSON string → score) into
 * `{ parsed, score }` entries, skipping keys that are not valid JSON.
 *
 * @private
 */
export function parseClauses(matchedQueries) {
    if (!matchedQueries) {
        return [];
    }

    return Object.keys(matchedQueries).flatMap((clause) => {
        try {
            return [{ parsed: JSON.parse(clause), score: parseFloat(matchedQueries[clause]) || 0 }];
        } catch {
            return [];
        }
    });
}

/**
 * A field clause is what the core panel explains — neither boost nor cross-entity
 * (AdvancedSearch's sections). Key presence, not truthiness: a boost of 0 is still a boost.
 *
 * @private
 */
export function isFieldClause(parsed) {
    return parsed !== null && typeof parsed === 'object' && !('boost' in parsed) && !('crossEntity' in parsed);
}
