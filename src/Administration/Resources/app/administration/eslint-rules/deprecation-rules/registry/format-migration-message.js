/**
 * @sw-package framework
 */

/**
 * Lists a migration's references, one `type: target` per line, or '' when it has none.
 *
 * @example
 * formatReferences({ references: [{ type: 'pull-request', target: '#123' }] }); // 'pull-request: #123'
 */
function formatReferences(migration) {
    if (!migration.references?.length) {
        return '';
    }

    return migration.references.map((reference) => `${reference.type}: ${reference.target}`).join('\n');
}

/**
 * Joins a rule's headline lines with the registry context every deprecation report carries:
 * the migration description, the removal version and the references. Empty lines are dropped.
 *
 * @example
 * appendRegistryContext(['The "foo" API is deprecated.'], migration);
 * // 'The "foo" API is deprecated.\n<description>\nRemoved in Shopware v6.8.0.0.'
 */
function appendRegistryContext(lines, migration) {
    const references = formatReferences(migration);

    return [
        ...lines,
        migration.description,
        `Removed in Shopware ${migration.removedIn}.`,
        references ? `References:\n${references}` : '',
    ]
        .filter(Boolean)
        .join('\n');
}

module.exports = {
    appendRegistryContext,
};
