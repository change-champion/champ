<?php

declare(strict_types=1);

namespace ChangeChampion\Services;

use ChangeChampion\Models\Changeset;

class ConventionalCommitParser
{
    /**
     * Commit types that should generate changesets.
     */
    private const RELEASE_TYPES = [
        'feat' => Changeset::TYPE_MINOR,
        'fix' => Changeset::TYPE_PATCH,
        'perf' => Changeset::TYPE_PATCH,
        'refactor' => Changeset::TYPE_PATCH,
    ];

    /**
     * Commit types that should be ignored (no changeset).
     */
    private const IGNORED_TYPES = ['docs', 'test', 'tests', 'chore', 'ci', 'style', 'build'];

    /**
     * Parse a conventional commit message.
     *
     * @return null|array{type: null|string, scope: null|string, breaking: bool, description: string, body: null|string}
     */
    public function parse(string $message): ?array
    {
        $lines = explode("\n", trim($message));
        $firstLine = $lines[0];
        $body = count($lines) > 1 ? implode("\n", array_slice($lines, 1)) : null;

        // Pattern: type(scope)!: description
        // Examples: feat: add feature, fix(auth): fix bug, feat!: breaking change
        $pattern = '/^(?<type>[a-z]+)(?:\((?<scope>[^)]+)\))?(?<breaking>!)?\s*:\s*(?<description>.+)$/i';

        if (!preg_match($pattern, $firstLine, $matches)) {
            return null;
        }

        $breaking = !empty($matches['breaking']);

        // Also check for BREAKING CHANGE in body
        if ($body && preg_match('/^BREAKING[ -]CHANGE\s*:/mi', $body)) {
            $breaking = true;
        }

        $scope = $matches['scope'] ?? null;

        return [
            'type' => strtolower($matches['type']),
            'scope' => $scope ?: null,
            'breaking' => $breaking,
            'description' => trim($matches['description']),
            'body' => $body ? trim($body) : null,
        ];
    }

    /**
     * Determine the changeset type from a parsed commit.
     *
     * @return null|string The changeset type (major, minor, patch) or null if commit should be ignored
     */
    public function getChangesetType(array $parsed): ?string
    {
        // Breaking changes are always major
        if ($parsed['breaking']) {
            return Changeset::TYPE_MAJOR;
        }

        $commitType = $parsed['type'];

        // Check if this type should be ignored
        if (in_array($commitType, self::IGNORED_TYPES, true)) {
            return null;
        }

        // Map to changeset type
        return self::RELEASE_TYPES[$commitType] ?? null;
    }

    /**
     * Format the commit as a changeset summary.
     */
    public function formatSummary(array $parsed): string
    {
        $summary = $parsed['description'];

        // Add scope as prefix if present
        if ($parsed['scope']) {
            $summary = "**{$parsed['scope']}**: {$summary}";
        }

        // Extract issue references from commit body/footer (e.g., "Fixes #123", "Closes #45")
        if ($parsed['body']) {
            preg_match_all('/(?:Fix(?:es)?|Close[sd]?|Resolve[sd]?|Refs?)\s+#(\d+)/i', $parsed['body'], $matches);

            foreach ($matches[1] as $issueNum) {
                if (!str_contains($summary, '#'.$issueNum)) {
                    $summary .= ' (#'.$issueNum.')';
                }
            }
        }

        return $summary;
    }
}
