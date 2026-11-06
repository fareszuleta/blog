<?php
declare(strict_types=1);

/**
 * Real, public agent skills hosted in /skills (unmodified copies, with attribution).
 * Data lives in skills_catalog.json: name, category, description, about, use_when,
 * example, author, license, source.
 */
function skills_catalog(): array
{
    $json = file_get_contents(__DIR__ . '/skills_catalog.json');
    return json_decode($json === false ? '[]' : $json, true) ?: [];
}

/** Names of the placeholder skills that earlier versions of the site seeded. */
function legacy_skill_names(): array
{
    return ['pdf-tables', 'lcp-audit', 'memory-gc', 'flaky-test-finder', 'deploy-rollback', 'changelog-draft',
        'schema-diff', 'citation-check', 'a11y-check', 'design-tokens', 'responsive-check', 'commit-splitter',
        'pr-describer', 'dependency-audit', 'secrets-scan', 'sql-explain', 'api-contract-diff', 'log-triage',
        'dockerfile-slim', 'seo-meta', 'readme-doctor', 'translate-keep-code', 'meeting-notes', 'prompt-regression'];
}
