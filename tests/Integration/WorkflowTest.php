<?php

declare(strict_types=1);

namespace ChangeChampion\Tests\Integration;

use ChangeChampion\Services\ChangelogGenerator;
use ChangeChampion\Services\ChangesetManager;
use ChangeChampion\Services\ConfigManager;
use ChangeChampion\Services\VersionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class WorkflowTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/change-champion-workflow-'.uniqid();
        mkdir($this->tempDir, 0o755, true);

        // Create a minimal composer.json
        file_put_contents($this->tempDir.'/composer.json', json_encode([
            'name' => 'test/package',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testFullWorkflow(): void
    {
        $configManager = new ConfigManager($this->tempDir);
        $changesetManager = new ChangesetManager($configManager);
        $versionCalculator = new VersionCalculator();
        $changelogGenerator = new ChangelogGenerator($this->tempDir);

        // Step 1: Initialize
        $this->assertFalse($configManager->isInitialized());
        $configManager->initialize();
        $this->assertTrue($configManager->isInitialized());

        // Step 2: Add changesets
        $changeset1 = $changesetManager->create('minor', 'Add user authentication');
        $changeset2 = $changesetManager->create('patch', 'Fix login validation');

        $this->assertFileExists($changeset1->filePath);
        $this->assertFileExists($changeset2->filePath);

        // Step 3: Check status
        $changesets = $changesetManager->getAll();
        $this->assertCount(2, $changesets);

        $currentVersion = $configManager->getCurrentVersion();
        $this->assertSame('1.0.0', $currentVersion);

        $nextVersion = $versionCalculator->calculateNextVersion($currentVersion, $changesets);
        $this->assertSame('1.1.0', $nextVersion); // minor bump

        // Step 4: Apply version
        // Update composer.json
        $composerJson = $configManager->getComposerJson();
        $composerJson['version'] = $nextVersion;
        $configManager->saveComposerJson($composerJson);

        // Generate changelog
        $changelogGenerator->update($nextVersion, $changesets);

        // Delete changesets
        $changesetManager->deleteAll();

        // Verify results
        $this->assertSame('1.1.0', $configManager->getCurrentVersion());
        $this->assertFileExists($this->tempDir.'/CHANGELOG.md');
        $this->assertEmpty($changesetManager->getAll());

        $changelog = file_get_contents($this->tempDir.'/CHANGELOG.md');
        $this->assertStringContainsString('## 1.1.0', $changelog);
        $this->assertStringContainsString('Add user authentication', $changelog);
        $this->assertStringContainsString('Fix login validation', $changelog);
    }

    public function testMultipleVersionBumps(): void
    {
        $configManager = new ConfigManager($this->tempDir);
        $changesetManager = new ChangesetManager($configManager);
        $versionCalculator = new VersionCalculator();
        $changelogGenerator = new ChangelogGenerator($this->tempDir);

        $configManager->initialize();

        // First release: 1.0.0 -> 1.1.0
        $changesetManager->create('minor', 'Feature A');
        $changesets = $changesetManager->getAll();
        $version1 = $versionCalculator->calculateNextVersion('1.0.0', $changesets);

        $composerJson = $configManager->getComposerJson();
        $composerJson['version'] = $version1;
        $configManager->saveComposerJson($composerJson);
        $changelogGenerator->update($version1, $changesets);
        $changesetManager->deleteAll();

        $this->assertSame('1.1.0', $configManager->getCurrentVersion());

        // Second release: 1.1.0 -> 2.0.0
        $changesetManager->create('major', 'Breaking change');
        $changesets = $changesetManager->getAll();
        $version2 = $versionCalculator->calculateNextVersion($configManager->getCurrentVersion(), $changesets);

        $composerJson = $configManager->getComposerJson();
        $composerJson['version'] = $version2;
        $configManager->saveComposerJson($composerJson);
        $changelogGenerator->update($version2, $changesets);
        $changesetManager->deleteAll();

        $this->assertSame('2.0.0', $configManager->getCurrentVersion());

        // Check changelog has both versions
        $changelog = file_get_contents($this->tempDir.'/CHANGELOG.md');
        $this->assertStringContainsString('## 2.0.0', $changelog);
        $this->assertStringContainsString('## 1.1.0', $changelog);

        // 2.0.0 should appear before 1.1.0
        $pos200 = strpos($changelog, '## 2.0.0');
        $pos110 = strpos($changelog, '## 1.1.0');
        $this->assertLessThan($pos110, $pos200);
    }

    public function testChangesetPersistence(): void
    {
        $configManager = new ConfigManager($this->tempDir);
        $configManager->initialize();

        $changesetManager1 = new ChangesetManager($configManager);
        $created = $changesetManager1->create('patch', 'Fix something');

        // Create new manager instance (simulating new command run)
        $changesetManager2 = new ChangesetManager($configManager);
        $changesets = $changesetManager2->getAll();

        $this->assertCount(1, $changesets);
        $this->assertSame($created->id, $changesets[0]->id);
        $this->assertSame('patch', $changesets[0]->type);
        $this->assertSame('Fix something', $changesets[0]->summary);
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
