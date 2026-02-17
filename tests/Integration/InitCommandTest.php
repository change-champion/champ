<?php

declare(strict_types=1);

namespace ChangeChampion\Tests\Integration;

use ChangeChampion\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @coversNothing
 */
class InitCommandTest extends TestCase
{
    private string $tempDir;
    private string $originalDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/change-champion-init-test-'.uniqid();
        mkdir($this->tempDir, 0o755, true);

        // Create a minimal composer.json
        file_put_contents($this->tempDir.'/composer.json', json_encode([
            'name' => 'test/package',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));

        // Store original directory and change to temp
        $this->originalDir = getcwd();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        $this->removeDirectory($this->tempDir);
    }

    public function testInitCreatesChangesDirectory(): void
    {
        $application = new Application();
        $command = $application->find('init');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertDirectoryExists($this->tempDir.'/.changes');
        $this->assertFileExists($this->tempDir.'/.changes/config.json');
        $this->assertFileExists($this->tempDir.'/.changes/README.md');
    }

    public function testInitWithGithubActionsCreatesWorkflows(): void
    {
        $application = new Application();
        $command = $application->find('init');
        $tester = new CommandTester($command);

        $tester->execute(['--with-github-actions' => true]);

        $this->assertDirectoryExists($this->tempDir.'/.github/workflows');
        $this->assertFileExists($this->tempDir.'/.github/workflows/change-champion.yml');

        $content = file_get_contents($this->tempDir.'/.github/workflows/change-champion.yml');
        $this->assertStringNotContainsString('{{version}}', $content);
        $this->assertMatchesRegularExpression('/@v\d+/', $content);
    }

    public function testInitWithGithubActionsDoesNotOverwriteExisting(): void
    {
        // Create existing workflow
        mkdir($this->tempDir.'/.github/workflows', 0o755, true);
        file_put_contents(
            $this->tempDir.'/.github/workflows/change-champion.yml',
            'existing content'
        );

        $application = new Application();
        $command = $application->find('init');
        $tester = new CommandTester($command);

        $tester->execute(['--with-github-actions' => true]);

        // Should not overwrite existing file
        $this->assertSame(
            'existing content',
            file_get_contents($this->tempDir.'/.github/workflows/change-champion.yml')
        );
    }

    public function testInitFailsWithoutComposerJson(): void
    {
        unlink($this->tempDir.'/composer.json');

        $application = new Application();
        $command = $application->find('init');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('No composer.json found', $tester->getDisplay());
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
