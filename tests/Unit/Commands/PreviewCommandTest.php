<?php

declare(strict_types=1);

namespace ChangeChampion\Tests\Unit\Commands;

use ChangeChampion\Commands\PreviewCommand;
use ChangeChampion\Services\ConfigManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @coversNothing
 */
class PreviewCommandTest extends TestCase
{
    private string $tempDir;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/change-champion-preview-'.uniqid();
        mkdir($this->tempDir, 0o755, true);

        // Create composer.json
        file_put_contents($this->tempDir.'/composer.json', json_encode([
            'name' => 'test/package',
            'version' => '1.0.0',
        ]));

        // Initialize changesets
        $configManager = new ConfigManager($this->tempDir);
        $configManager->initialize();

        // Change to temp directory for the command
        chdir($this->tempDir);

        $application = new Application();
        $application->add(new PreviewCommand());
        $command = $application->find('preview');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testPreviewWithNoChangesets(): void
    {
        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('No changesets found', $this->commandTester->getDisplay());
    }

    public function testPreviewWithChangeset(): void
    {
        $this->createChangeset('feature', 'minor', 'Add awesome feature');

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('CHANGELOG Preview', $output);
        $this->assertStringContainsString('Current version: 1.0.0', $output);
        $this->assertStringContainsString('Next version: 1.1.0', $output);
        $this->assertStringContainsString('### Features', $output);
        $this->assertStringContainsString('Add awesome feature', $output);
    }

    public function testPreviewWithPrerelease(): void
    {
        $this->createChangeset('feature', 'minor', 'Add awesome feature');

        $this->commandTester->execute(['--prerelease' => 'alpha']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Next version: 1.1.0-alpha.1', $output);
    }

    public function testPreviewWithInvalidPrerelease(): void
    {
        $this->createChangeset('feature', 'minor', 'Add awesome feature');

        $this->commandTester->execute(['--prerelease' => 'invalid']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid prerelease type', $this->commandTester->getDisplay());
    }

    public function testPreviewWithMultipleChangesets(): void
    {
        $this->createChangeset('feature1', 'minor', 'Add feature one');
        $this->createChangeset('feature2', 'minor', 'Add feature two');
        $this->createChangeset('bugfix', 'patch', 'Fix a bug');

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('### Features', $output);
        $this->assertStringContainsString('Add feature one', $output);
        $this->assertStringContainsString('Add feature two', $output);
        $this->assertStringContainsString('### Fixes', $output);
        $this->assertStringContainsString('Fix a bug', $output);
    }

    private function createChangeset(string $name, string $type, string $summary): void
    {
        $content = "---\ntype: {$type}\n---\n\n{$summary}";
        file_put_contents($this->tempDir."/.changes/{$name}.md", $content);
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
