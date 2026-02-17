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
class InstallSkillCommandTest extends TestCase
{
    private string $tempDir;
    private string $originalDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/change-champion-install-skill-test-'.uniqid();
        mkdir($this->tempDir, 0o755, true);

        $this->originalDir = getcwd();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        $this->removeDirectory($this->tempDir);
    }

    public function testInstallSkillCreatesSkillFile(): void
    {
        $application = new Application();
        $command = $application->find('install-skill');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertFileExists($this->tempDir.'/.claude/skills/change-champion/SKILL.md');
        $this->assertStringContainsString('Claude skill installed', $tester->getDisplay());
    }

    public function testInstallSkillSkipsWhenFileExists(): void
    {
        mkdir($this->tempDir.'/.claude/skills/change-champion', 0o755, true);
        file_put_contents(
            $this->tempDir.'/.claude/skills/change-champion/SKILL.md',
            'existing content'
        );

        $application = new Application();
        $command = $application->find('install-skill');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(
            'existing content',
            file_get_contents($this->tempDir.'/.claude/skills/change-champion/SKILL.md')
        );
        $this->assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testInstallSkillForceOverwritesExistingFile(): void
    {
        mkdir($this->tempDir.'/.claude/skills/change-champion', 0o755, true);
        file_put_contents(
            $this->tempDir.'/.claude/skills/change-champion/SKILL.md',
            'existing content'
        );

        $application = new Application();
        $command = $application->find('install-skill');
        $tester = new CommandTester($command);

        $tester->execute(['--force' => true]);

        $this->assertNotSame(
            'existing content',
            file_get_contents($this->tempDir.'/.claude/skills/change-champion/SKILL.md')
        );
        $this->assertStringContainsString('Claude skill installed', $tester->getDisplay());
    }

    public function testInstalledSkillMatchesSource(): void
    {
        $application = new Application();
        $command = $application->find('install-skill');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $sourcePath = realpath(__DIR__.'/../../resources/skills/change-champion/SKILL.md');
        $this->assertFileEquals($sourcePath, $this->tempDir.'/.claude/skills/change-champion/SKILL.md');
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
