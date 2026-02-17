<?php

declare(strict_types=1);

namespace ChangeChampion\Tests\Unit\Commands;

use ChangeChampion\Commands\CheckCommand;
use ChangeChampion\Services\ConfigManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @coversNothing
 */
class CheckCommandTest extends TestCase
{
    private string $tempDir;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/change-champion-check-'.uniqid();
        mkdir($this->tempDir, 0o755, true);

        // Create composer.json
        file_put_contents($this->tempDir.'/composer.json', json_encode([
            'name' => 'test/package',
        ]));

        // Initialize changesets
        $configManager = new ConfigManager($this->tempDir);
        $configManager->initialize();

        // Change to temp directory for the command
        chdir($this->tempDir);

        $application = new Application();
        $application->add(new CheckCommand());
        $command = $application->find('check');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testCheckWithNoChangesets(): void
    {
        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('No changeset files found', $this->commandTester->getDisplay());
    }

    public function testCheckWithValidChangeset(): void
    {
        $this->createChangeset('valid-test', 'minor', 'A valid changeset');

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('All 1 changeset(s) are valid', $this->commandTester->getDisplay());
    }

    public function testCheckWithInvalidFormat(): void
    {
        file_put_contents($this->tempDir.'/.changes/invalid.md', 'no frontmatter here');

        $this->commandTester->execute([]);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid changeset format', $this->commandTester->getDisplay());
    }

    public function testCheckWithInvalidType(): void
    {
        file_put_contents($this->tempDir.'/.changes/bad-type.md', "---\ntype: invalid\n---\n\nSome summary");

        $this->commandTester->execute([]);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString("Invalid type 'invalid'", $this->commandTester->getDisplay());
    }

    public function testCheckWithEmptySummary(): void
    {
        file_put_contents($this->tempDir.'/.changes/empty-summary.md', "---\ntype: patch\n---\n\n   ");

        $this->commandTester->execute([]);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('summary is empty', $this->commandTester->getDisplay());
    }

    public function testCheckWithMixedValidAndInvalid(): void
    {
        $this->createChangeset('valid', 'patch', 'Valid changeset');
        file_put_contents($this->tempDir.'/.changes/invalid.md', 'no frontmatter');

        $this->commandTester->execute([]);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('1 changeset(s) valid, 1 invalid', $this->commandTester->getDisplay());
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
