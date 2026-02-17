<?php

declare(strict_types=1);

namespace ChangeChampion\Commands;

use ChangeChampion\Services\ChangesetManager;
use ChangeChampion\Services\ConfigManager;
use ChangeChampion\Services\VersionCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'status',
    description: 'Show pending changesets and calculated next version'
)]
class StatusCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $basePath = getcwd();

        $configManager = new ConfigManager($basePath);
        $changesetManager = new ChangesetManager($configManager);
        $versionCalculator = new VersionCalculator();

        if (!$configManager->isInitialized()) {
            $io->error('Changesets not initialized. Run "champ init" first.');

            return Command::FAILURE;
        }

        $changesets = $changesetManager->getAll();
        $currentVersion = $configManager->getCurrentVersion();
        $packageName = $configManager->getPackageName();

        $io->title('Changeset Status');

        $io->text([
            "Package: <info>{$packageName}</info>",
            "Current version: <info>{$currentVersion}</info>",
        ]);

        if (empty($changesets)) {
            $io->newLine();
            $io->note('No pending changesets found.');
            $io->text('Run "champ add" to create a new changeset.');

            return Command::SUCCESS;
        }

        $nextVersion = $versionCalculator->calculateNextVersion($currentVersion, $changesets);
        $bumpType = $versionCalculator->getHighestBumpType($changesets);

        $io->text([
            "Next version: <info>{$nextVersion}</info> ({$bumpType} bump)",
            '',
        ]);

        $io->section(sprintf('%d pending changeset(s)', count($changesets)));

        if ($output->isVerbose()) {
            foreach ($changesets as $changeset) {
                $io->text([
                    "<comment>{$changeset->id}</comment> ({$changeset->type})",
                    "  {$changeset->summary}",
                    '',
                ]);
            }
        } else {
            $rows = [];
            foreach ($changesets as $changeset) {
                $summary = strlen($changeset->summary) > 60
                    ? substr($changeset->summary, 0, 57).'...'
                    : $changeset->summary;
                $rows[] = [$changeset->id, $changeset->type, $summary];
            }
            $io->table(['ID', 'Type', 'Summary'], $rows);
        }

        $io->text('Run "champ version" to apply these changes.');

        return Command::SUCCESS;
    }
}
