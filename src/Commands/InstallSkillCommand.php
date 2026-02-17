<?php

declare(strict_types=1);

namespace ChangeChampion\Commands;

use ChangeChampion\Commands\Concerns\ResolvesResourceDir;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'install-skill',
    description: 'Install Claude skill file for change-champion into the current project'
)]
class InstallSkillCommand extends Command
{
    use ResolvesResourceDir;

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing skill file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $basePath = getcwd();
        $force = $input->getOption('force');

        $resourcesDir = $this->getResourcesDir();
        $sourcePath = $resourcesDir.'/skills/change-champion/SKILL.md';

        if (!file_exists($sourcePath)) {
            $io->error('Could not find skill template. Please copy it manually from the package.');

            return Command::FAILURE;
        }

        $skillDir = $basePath.'/.claude/skills/change-champion';
        $targetPath = $skillDir.'/SKILL.md';

        if (file_exists($targetPath) && !$force) {
            $io->text('  Skipped SKILL.md (already exists, use --force to overwrite)');

            return Command::SUCCESS;
        }

        if (!is_dir($skillDir)) {
            mkdir($skillDir, 0o755, true);
        }

        copy($sourcePath, $targetPath);
        $io->text('✓ Created .claude/skills/change-champion/SKILL.md');

        $io->success('Claude skill installed!');
        $io->note([
            'The skill file enables Claude to use change-champion commands.',
            'Commit this file to your repository to share with your team.',
        ]);

        return Command::SUCCESS;
    }
}
