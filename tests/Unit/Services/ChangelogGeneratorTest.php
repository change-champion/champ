<?php

declare(strict_types=1);

namespace ChangeChampion\Tests\Unit\Services;

use ChangeChampion\Models\Changeset;
use ChangeChampion\Models\CommitInfo;
use ChangeChampion\Services\ChangelogGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ChangelogGeneratorTest extends TestCase
{
    private string $tempDir;
    private ChangelogGenerator $generator;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/change-champion-test-'.uniqid();
        mkdir($this->tempDir, 0o755, true);
        $this->generator = new ChangelogGenerator($this->tempDir, 'TEST_CHANGELOG.md');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testGetChangelogPath(): void
    {
        $this->assertSame($this->tempDir.'/TEST_CHANGELOG.md', $this->generator->getChangelogPath());
    }

    public function testUpdateCreatesNewChangelog(): void
    {
        $changesets = [
            $this->createChangeset('minor', 'Add new feature'),
            $this->createChangeset('patch', 'Fix bug'),
        ];

        $this->generator->update('1.0.0', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        $this->assertStringContainsString('# Changelog', $content);
        $this->assertStringContainsString('## 1.0.0', $content);
        $this->assertStringContainsString('### Features', $content);
        $this->assertStringContainsString('Add new feature', $content);
        $this->assertStringContainsString('### Fixes', $content);
        $this->assertStringContainsString('Fix bug', $content);
    }

    public function testUpdateWithMajorChanges(): void
    {
        $changesets = [
            $this->createChangeset('major', 'Breaking API change'),
        ];

        $this->generator->update('2.0.0', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        $this->assertStringContainsString('### Breaking Changes', $content);
        $this->assertStringContainsString('Breaking API change', $content);
    }

    public function testUpdatePrependsToExistingChangelog(): void
    {
        // Create existing changelog
        $existingContent = <<<'CHANGELOG'
            # Changelog

            All notable changes to this project will be documented in this file.

            ## 0.1.0 - 2024-01-01

            ### Features

            - Initial release
            CHANGELOG;
        file_put_contents($this->generator->getChangelogPath(), $existingContent);

        $changesets = [
            $this->createChangeset('minor', 'New feature in 0.2.0'),
        ];

        $this->generator->update('0.2.0', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        // New version should come before old version
        $pos020 = strpos($content, '## 0.2.0');
        $pos010 = strpos($content, '## 0.1.0');

        $this->assertNotFalse($pos020);
        $this->assertNotFalse($pos010);
        $this->assertLessThan($pos010, $pos020);
        $this->assertStringContainsString('New feature in 0.2.0', $content);
        $this->assertStringContainsString('Initial release', $content);
    }

    public function testUpdateIncludesDate(): void
    {
        $changesets = [
            $this->createChangeset('patch', 'Fix something'),
        ];

        $this->generator->update('1.0.1', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());
        $today = date('Y-m-d');

        $this->assertStringContainsString("## 1.0.1 - {$today}", $content);
    }

    public function testUpdateGroupsChangesetsByType(): void
    {
        $changesets = [
            $this->createChangeset('patch', 'Fix 1'),
            $this->createChangeset('minor', 'Feature 1'),
            $this->createChangeset('patch', 'Fix 2'),
            $this->createChangeset('major', 'Breaking 1'),
            $this->createChangeset('minor', 'Feature 2'),
        ];

        $this->generator->update('2.0.0', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        // Check order: Breaking Changes -> Features -> Fixes
        $posBreaking = strpos($content, '### Breaking Changes');
        $posFeatures = strpos($content, '### Features');
        $posFixes = strpos($content, '### Fixes');

        $this->assertLessThan($posFeatures, $posBreaking);
        $this->assertLessThan($posFixes, $posFeatures);
    }

    public function testIssueLinkingWithRepositoryUrl(): void
    {
        $this->generator->setRepositoryUrl('https://github.com/owner/repo');

        $changesets = [
            $this->createChangeset('patch', 'Fix bug. Fixes #123'),
        ];

        $this->generator->update('1.0.1', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        $this->assertStringContainsString('[#123](https://github.com/owner/repo/issues/123)', $content);
    }

    public function testIssueLinkingMultipleIssues(): void
    {
        $this->generator->setRepositoryUrl('https://github.com/owner/repo');

        $changesets = [
            $this->createChangeset('patch', 'Fix bugs #1, #2 and #3'),
        ];

        $this->generator->update('1.0.1', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        $this->assertStringContainsString('[#1](https://github.com/owner/repo/issues/1)', $content);
        $this->assertStringContainsString('[#2](https://github.com/owner/repo/issues/2)', $content);
        $this->assertStringContainsString('[#3](https://github.com/owner/repo/issues/3)', $content);
    }

    public function testNoIssueLinkingWithoutRepositoryUrl(): void
    {
        // Don't set repository URL and use temp dir without git
        $changesets = [
            $this->createChangeset('patch', 'Fix bug #123'),
        ];

        $this->generator->update('1.0.1', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        // Should contain the raw #123, not linked
        $this->assertStringContainsString('#123', $content);
        $this->assertStringNotContainsString('[#123]', $content);
    }

    public function testUpdatePrependsWithoutIntroText(): void
    {
        // Changelog with no intro text between header and first version
        $existingContent = "# Changelog\n\n## v1.0.0 - 2024-01-01\n\n### Features\n\n- Initial release\n";
        file_put_contents($this->generator->getChangelogPath(), $existingContent);

        $this->generator->setVersionPrefix('v');

        $changesets = [
            $this->createChangeset('minor', 'New feature'),
        ];

        $this->generator->update('1.1.0', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        $pos110 = strpos($content, '## v1.1.0');
        $pos100 = strpos($content, '## v1.0.0');

        $this->assertNotFalse($pos110);
        $this->assertNotFalse($pos100);
        $this->assertLessThan($pos100, $pos110, 'New version should appear before the old version');
    }

    public function testUpdatePrependsWithMultipleExistingVersions(): void
    {
        // Changelog with multiple existing versions and no intro text
        $existingContent = <<<'CHANGELOG'
            # Changelog

            ## v2.0.0 - 2024-02-01

            ### Features

            - Feature in v2

            ## v1.0.0 - 2024-01-01

            ### Features

            - Initial release
            CHANGELOG;
        file_put_contents($this->generator->getChangelogPath(), $existingContent);

        $this->generator->setVersionPrefix('v');

        $changesets = [
            $this->createChangeset('patch', 'Fix bug'),
        ];

        $this->generator->update('2.0.1', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        $pos201 = strpos($content, '## v2.0.1');
        $pos200 = strpos($content, '## v2.0.0');
        $pos100 = strpos($content, '## v1.0.0');

        $this->assertNotFalse($pos201);
        $this->assertNotFalse($pos200);
        $this->assertNotFalse($pos100);
        $this->assertLessThan($pos200, $pos201, 'New version should appear before v2.0.0');
        $this->assertLessThan($pos100, $pos200, 'v2.0.0 should appear before v1.0.0');
    }

    public function testUpdatePrependsWithLinkedVersionHeaders(): void
    {
        // Changelog using conventional-changelog format with linked version headers
        $existingContent = <<<'CHANGELOG'
            # Changelog

            ## [1.1.0](https://github.com/owner/repo/compare/v1.0.0...v1.1.0) (2024-02-01)

            ### Features

            * some feature

            ## [1.0.0](https://github.com/owner/repo/compare/v0.1.0...v1.0.0) (2024-01-01)

            ### Features

            * initial release
            CHANGELOG;
        file_put_contents($this->generator->getChangelogPath(), $existingContent);

        $changesets = [
            $this->createChangeset('patch', 'Fix bug'),
        ];

        $this->generator->update('1.1.1', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        $posNew = strpos($content, '## 1.1.1');
        $pos110 = strpos($content, '## [1.1.0]');
        $pos100 = strpos($content, '## [1.0.0]');

        $this->assertNotFalse($posNew);
        $this->assertNotFalse($pos110);
        $this->assertNotFalse($pos100);
        $this->assertLessThan($pos110, $posNew, 'New version should appear before [1.1.0]');
        $this->assertLessThan($pos100, $pos110, '[1.1.0] should appear before [1.0.0]');
    }

    public function testGetLatestVersionWithLinkedHeaders(): void
    {
        $existingContent = "# Changelog\n\n## [1.2.3](https://github.com/owner/repo/compare/v1.2.2...v1.2.3) (2024-01-01)\n\n### Bug Fixes\n\n* fix\n";
        file_put_contents($this->generator->getChangelogPath(), $existingContent);

        $this->assertSame('1.2.3', $this->generator->getLatestVersion());
    }

    public function testIssueLinkingFromMultiLineSummary(): void
    {
        $this->generator->setRepositoryUrl('https://github.com/owner/repo');

        $changesets = [
            $this->createChangeset('patch', "Fix wildcard permissions\nFixes #45"),
        ];

        $this->generator->update('1.0.1', $changesets);

        $content = file_get_contents($this->generator->getChangelogPath());

        $this->assertStringContainsString('[#45](https://github.com/owner/repo/issues/45)', $content);
    }

    public function testCommitAndPrLinkingWithRepoUrl(): void
    {
        $this->generator->setRepositoryUrl('https://github.com/owner/repo');
        $this->generator->setCommitInfoMap([
            'test-1' => new CommitInfo(
                commitHash: 'abc1234567890def1234567890abcdef12345678',
                shortHash: 'abc1234',
                subject: 'fix: something (#45)',
                prNumber: 45,
            ),
        ]);

        $changeset = new Changeset(
            id: 'test-1',
            type: 'patch',
            summary: 'Fix something',
            filePath: '/tmp/test.md',
        );

        $entry = $this->generator->generateEntry('1.0.1', [$changeset]);

        $this->assertStringContainsString('[#45](https://github.com/owner/repo/pull/45)', $entry);
        $this->assertStringContainsString('[abc1234](https://github.com/owner/repo/commit/abc1234567890def1234567890abcdef12345678)', $entry);
    }

    public function testCommitAndPrRefsWithoutRepoUrl(): void
    {
        $this->generator->setCommitInfoMap([
            'test-1' => new CommitInfo(
                commitHash: 'abc1234567890def1234567890abcdef12345678',
                shortHash: 'abc1234',
                subject: 'fix: something (#45)',
                prNumber: 45,
            ),
        ]);

        $changeset = new Changeset(
            id: 'test-1',
            type: 'patch',
            summary: 'Fix something',
            filePath: '/tmp/test.md',
        );

        $entry = $this->generator->generateEntry('1.0.1', [$changeset]);

        $this->assertStringContainsString('(#45)', $entry);
        $this->assertStringContainsString('(abc1234)', $entry);
        $this->assertStringNotContainsString('[#45]', $entry);
        $this->assertStringNotContainsString('[abc1234]', $entry);
    }

    public function testPrNotDuplicatedWhenAlreadyInSummary(): void
    {
        $this->generator->setRepositoryUrl('https://github.com/owner/repo');
        $this->generator->setCommitInfoMap([
            'test-1' => new CommitInfo(
                commitHash: 'abc1234567890def1234567890abcdef12345678',
                shortHash: 'abc1234',
                subject: 'fix: something (#45)',
                prNumber: 45,
            ),
        ]);

        $changeset = new Changeset(
            id: 'test-1',
            type: 'patch',
            summary: "Fix wildcard permissions\nFixes #45",
            filePath: '/tmp/test.md',
        );

        $entry = $this->generator->generateEntry('1.0.1', [$changeset]);

        // #45 should appear once (as issue link), not duplicated as PR link
        $this->assertSame(1, substr_count($entry, '#45'));
        // Commit hash should still appear
        $this->assertStringContainsString('[abc1234](https://github.com/owner/repo/commit/', $entry);
    }

    public function testCommitOnlyWhenNoPrNumber(): void
    {
        $this->generator->setRepositoryUrl('https://github.com/owner/repo');
        $this->generator->setCommitInfoMap([
            'test-1' => new CommitInfo(
                commitHash: 'abc1234567890def1234567890abcdef12345678',
                shortHash: 'abc1234',
                subject: 'manual commit without PR',
            ),
        ]);

        $changeset = new Changeset(
            id: 'test-1',
            type: 'patch',
            summary: 'Fix something',
            filePath: '/tmp/test.md',
        );

        $entry = $this->generator->generateEntry('1.0.1', [$changeset]);

        $this->assertStringNotContainsString('/pull/', $entry);
        $this->assertStringContainsString('[abc1234](https://github.com/owner/repo/commit/', $entry);
    }

    public function testGracefulFallbackWithNoCommitInfo(): void
    {
        $this->generator->setRepositoryUrl('https://github.com/owner/repo');

        $changeset = new Changeset(
            id: 'test-1',
            type: 'patch',
            summary: 'Fix something',
            filePath: '/tmp/test.md',
        );

        $entry = $this->generator->generateEntry('1.0.1', [$changeset]);

        $this->assertStringContainsString('- Fix something', $entry);
        $this->assertStringNotContainsString('/commit/', $entry);
        $this->assertStringNotContainsString('/pull/', $entry);
    }

    public function testMergeCommitPrExtraction(): void
    {
        $this->generator->setRepositoryUrl('https://github.com/owner/repo');
        $this->generator->setCommitInfoMap([
            'test-1' => new CommitInfo(
                commitHash: 'def4567890123abc4567890123abcdef45678901',
                shortHash: 'def4567',
                subject: 'Merge pull request #99 from owner/feature-branch',
                prNumber: 99,
            ),
        ]);

        $changeset = new Changeset(
            id: 'test-1',
            type: 'minor',
            summary: 'Add feature X',
            filePath: '/tmp/test.md',
        );

        $entry = $this->generator->generateEntry('1.1.0', [$changeset]);

        $this->assertStringContainsString('[#99](https://github.com/owner/repo/pull/99)', $entry);
        $this->assertStringContainsString('[def4567](https://github.com/owner/repo/commit/', $entry);
    }

    private function createChangeset(string $type, string $summary): Changeset
    {
        return new Changeset(
            id: 'test-'.uniqid(),
            type: $type,
            summary: $summary,
            filePath: '/tmp/test.md'
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
