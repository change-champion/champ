<?php

declare(strict_types=1);

namespace ChangeChampion\Services;

use ChangeChampion\Models\Changeset;
use ChangeChampion\Models\CommitInfo;
use ChangeChampion\Models\Config;

class ChangelogGenerator
{
    private ?string $repositoryUrl = null;
    private array $sections = Config::DEFAULT_SECTIONS;
    private string $versionPrefix = '';

    /** @var null|array<string, CommitInfo> */
    private ?array $commitInfoOverride = null;

    public function __construct(
        private readonly string $basePath,
        private readonly string $filename = 'CHANGELOG.md',
    ) {}

    /**
     * Set the repository URL for linking issues.
     */
    public function setRepositoryUrl(?string $url): void
    {
        $this->repositoryUrl = $url;
    }

    /**
     * Set custom section headers.
     */
    public function setSections(array $sections): void
    {
        $this->sections = $sections;
    }

    /**
     * Set version prefix for changelog headers (e.g., "v" for v1.0.0).
     */
    public function setVersionPrefix(string $prefix): void
    {
        $this->versionPrefix = $prefix;
    }

    /**
     * Override commit info lookup (for testing).
     *
     * @param array<string, CommitInfo> $map Map of changeset ID to CommitInfo
     */
    public function setCommitInfoMap(array $map): void
    {
        $this->commitInfoOverride = $map;
    }

    /**
     * Get the repository URL, auto-detecting from git if not set.
     */
    public function getRepositoryUrl(): ?string
    {
        if (null !== $this->repositoryUrl) {
            return $this->repositoryUrl;
        }

        // Try to auto-detect from git remote
        return $this->detectRepositoryUrl();
    }

    public function getChangelogPath(): string
    {
        return $this->basePath.'/'.$this->filename;
    }

    /**
     * Get the latest version from CHANGELOG.md.
     */
    public function getLatestVersion(): ?string
    {
        $content = $this->getExistingContent();

        if (empty($content)) {
            return null;
        }

        // Match first version header
        // Supports: "## 1.2.3", "## v1.2.3", "## [1.2.3](...)", "## [v1.2.3](...)", with optional prerelease
        if (preg_match('/^## \[?v?(\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)\.\d+)?)\]?/m', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Generate or update the CHANGELOG.md file.
     *
     * @param string      $version    The new version
     * @param Changeset[] $changesets The changesets to include
     */
    public function update(string $version, array $changesets): void
    {
        $newEntry = $this->generateEntry($version, $changesets);
        $existingContent = $this->getExistingContent();

        $newContent = $this->mergeContent($newEntry, $existingContent);

        file_put_contents($this->getChangelogPath(), $newContent);
    }

    /**
     * Generate a changelog entry for a version.
     *
     * @param Changeset[] $changesets
     */
    public function generateEntry(string $version, array $changesets): string
    {
        $date = date('Y-m-d');
        $lines = [];
        $lines[] = "## {$this->versionPrefix}{$version} - {$date}";
        $lines[] = '';

        // Look up commit data for all changesets
        $commitInfoMap = $this->lookupCommitInfo($changesets);

        // Group changesets by type
        $grouped = [
            Changeset::TYPE_MAJOR => [],
            Changeset::TYPE_MINOR => [],
            Changeset::TYPE_PATCH => [],
        ];

        foreach ($changesets as $changeset) {
            $grouped[$changeset->type][] = $changeset;
        }

        // Add sections in order: major, minor, patch
        foreach ([Changeset::TYPE_MAJOR, Changeset::TYPE_MINOR, Changeset::TYPE_PATCH] as $type) {
            if (!empty($grouped[$type])) {
                $lines[] = '### '.$this->getSectionHeader($type);
                $lines[] = '';
                foreach ($grouped[$type] as $changeset) {
                    $commitInfo = $commitInfoMap[$changeset->id] ?? null;
                    $lines[] = '- '.$this->formatSummary($changeset->summary, $commitInfo);
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get section header for a changeset type.
     */
    private function getSectionHeader(string $type): string
    {
        return $this->sections[$type] ?? ucfirst($type);
    }

    /**
     * Detect repository URL from git remote.
     */
    private function detectRepositoryUrl(): ?string
    {
        $output = [];
        $returnCode = 0;

        exec('git -C '.escapeshellarg($this->basePath).' remote get-url origin 2>/dev/null', $output, $returnCode);

        if (0 !== $returnCode || empty($output)) {
            return null;
        }

        $remoteUrl = trim($output[0]);

        // Convert SSH URL to HTTPS URL
        // git@github.com:owner/repo.git -> https://github.com/owner/repo
        if (preg_match('/^git@([^:]+):(.+?)(?:\.git)?$/', $remoteUrl, $matches)) {
            return 'https://'.$matches[1].'/'.$matches[2];
        }

        // Already HTTPS URL, strip .git suffix
        // https://github.com/owner/repo.git -> https://github.com/owner/repo
        if (preg_match('/^https?:\/\/(.+?)(?:\.git)?$/', $remoteUrl, $matches)) {
            return 'https://'.$matches[1];
        }

        return null;
    }

    private function formatSummary(string $summary, ?CommitInfo $commitInfo = null): string
    {
        // Take first line only for bullet points
        $lines = explode("\n", trim($summary));
        $text = trim($lines[0]);

        // Extract issue references from subsequent lines so they aren't lost
        if (count($lines) > 1) {
            $remainingText = implode(' ', array_slice($lines, 1));

            if (preg_match_all('/#(\d+)/', $remainingText, $matches)) {
                foreach ($matches[1] as $issueNum) {
                    if (!str_contains($text, '#'.$issueNum)) {
                        $text .= ' (#'.$issueNum.')';
                    }
                }
            }
        }

        // Link issue references if repository URL is available
        $repoUrl = $this->getRepositoryUrl();
        if (null !== $repoUrl) {
            $text = $this->linkIssues($text, $repoUrl);
        }

        // Append PR and commit references from git data
        if (null !== $commitInfo) {
            $text = $this->appendCommitReferences($text, $commitInfo, $repoUrl);
        }

        return $text;
    }

    /**
     * Convert issue references to markdown links.
     *
     * Patterns matched:
     * - #123
     * - Fixes #123
     * - Closes #123
     * - Resolves #123
     */
    private function linkIssues(string $text, string $repoUrl): string
    {
        // Match issue references that aren't already linked
        // Negative lookbehind to avoid matching already-linked issues like [#123](url)
        return preg_replace_callback(
            '/(?<!\[)#(\d+)(?!\])/',
            fn ($matches) => '[#'.$matches[1].']('.$repoUrl.'/issues/'.$matches[1].')',
            $text
        );
    }

    /**
     * Append PR number and commit hash references to the entry text.
     */
    private function appendCommitReferences(string $text, CommitInfo $commitInfo, ?string $repoUrl): string
    {
        // Add PR reference if not already mentioned in the text
        if (null !== $commitInfo->prNumber) {
            $prNum = (string) $commitInfo->prNumber;

            if (!str_contains($text, '#'.$prNum)) {
                if (null !== $repoUrl) {
                    $text .= ' ([#'.$prNum.']('.$repoUrl.'/pull/'.$prNum.'))';
                } else {
                    $text .= ' (#'.$prNum.')';
                }
            }
        }

        // Always add commit hash reference
        if (null !== $repoUrl) {
            $text .= ' (['.$commitInfo->shortHash.']('.$repoUrl.'/commit/'.$commitInfo->commitHash.'))';
        } else {
            $text .= ' ('.$commitInfo->shortHash.')';
        }

        return $text;
    }

    /**
     * Look up git commit data for each changeset file.
     *
     * @param Changeset[] $changesets
     *
     * @return array<string, CommitInfo> Map of changeset ID to CommitInfo
     */
    private function lookupCommitInfo(array $changesets): array
    {
        if (null !== $this->commitInfoOverride) {
            return $this->commitInfoOverride;
        }

        $map = [];

        foreach ($changesets as $changeset) {
            $info = $this->getCommitInfoForFile($changeset->filePath);
            if (null !== $info) {
                $map[$changeset->id] = $info;
            }
        }

        return $map;
    }

    /**
     * Get commit info for a specific file path.
     */
    private function getCommitInfoForFile(string $filePath): ?CommitInfo
    {
        // Convert absolute path to path relative to basePath
        $relativePath = $filePath;
        if (str_starts_with($filePath, $this->basePath.'/')) {
            $relativePath = substr($filePath, strlen($this->basePath) + 1);
        }

        $output = [];
        $returnCode = 0;

        // %H = full hash, %x01 = separator, %s = commit subject
        exec(
            'git -C '.escapeshellarg($this->basePath)
            .' log -1 --format=%H%x01%s -- '.escapeshellarg($relativePath)
            .' 2>/dev/null',
            $output,
            $returnCode
        );

        if (0 !== $returnCode || empty($output)) {
            return null;
        }

        $parts = explode("\x01", $output[0], 2);
        if (2 !== count($parts)) {
            return null;
        }

        [$fullHash, $subject] = $parts;

        return new CommitInfo(
            commitHash: $fullHash,
            shortHash: substr($fullHash, 0, 7),
            subject: $subject,
            prNumber: $this->extractPrNumber($subject),
        );
    }

    /**
     * Extract PR number from a commit subject line.
     *
     * Supports:
     * - Squash merge: "feat: some change (#45)"
     * - Merge commit: "Merge pull request #45 from owner/branch"
     */
    private function extractPrNumber(string $subject): ?int
    {
        // Squash merge: (#NN) at end of subject
        if (preg_match('/\(#(\d+)\)\s*$/', $subject, $matches)) {
            return (int) $matches[1];
        }

        // Merge commit: "Merge pull request #NN"
        if (preg_match('/^Merge pull request #(\d+)\b/', $subject, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function getExistingContent(): string
    {
        $path = $this->getChangelogPath();

        if (!file_exists($path)) {
            return '';
        }

        return file_get_contents($path);
    }

    private function mergeContent(string $newEntry, string $existingContent): string
    {
        if (empty($existingContent)) {
            // Create new changelog with header
            return "# Changelog\n\nAll notable changes to this project will be documented in this file.\n\n".$newEntry;
        }

        // Find the first version header and insert before it
        // Supports: ## 1.2.3, ## v1.2.3, ## [1.2.3](...), ## [v1.2.3](...), with optional prerelease
        if (preg_match('/^## \[?v?\d+\.\d+\.\d+/m', $existingContent, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPos = $matches[0][1];

            return substr($existingContent, 0, intval($insertPos)).$newEntry."\n".substr($existingContent, intval($insertPos));
        }

        // If no version header found, just append after any header content
        if (preg_match('/^(# .+?\n\n)/s', $existingContent, $matches)) {
            return $matches[1].$newEntry;
        }

        // Fallback: prepend header and new entry
        return "# Changelog\n\nAll notable changes to this project will be documented in this file.\n\n".$newEntry.$existingContent;
    }
}
