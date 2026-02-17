<?php

declare(strict_types=1);

namespace ChangeChampion\Commands;

use ChangeChampion\Services\ChangelogGenerator;
use ChangeChampion\Services\ChangesetManager;
use ChangeChampion\Services\ConfigManager;
use ChangeChampion\Services\VersionCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'preview',
    description: 'Preview the CHANGELOG entry that would be generated'
)]
class PreviewCommand extends Command
{
    private const VALID_PRERELEASES = ['alpha', 'beta', 'rc'];

    protected function configure(): void
    {
        $this
            ->addOption('prerelease', 'p', InputOption::VALUE_REQUIRED, 'Preview as pre-release version (alpha, beta, rc)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $basePath = getcwd();

        $configManager = new ConfigManager($basePath);
        $changesetManager = new ChangesetManager($configManager);
        $versionCalculator = new VersionCalculator();
        $changelogGenerator = new ChangelogGenerator($basePath);

        if (!$configManager->isInitialized()) {
            $io->error('Changesets not initialized. Run "champ init" first.');

            return Command::FAILURE;
        }

        $changesets = $changesetManager->getAll();
        $prerelease = $input->getOption('prerelease');

        // Validate prerelease option
        if (null !== $prerelease && !in_array($prerelease, self::VALID_PRERELEASES, true)) {
            $io->error('Invalid prerelease type. Use: alpha, beta, or rc');

            return Command::FAILURE;
        }

        $config = $configManager->getConfig();
        $changelogGenerator->setRepositoryUrl($config->repository);
        $changelogGenerator->setSections($config->sections);
        $changelogGenerator->setVersionPrefix($config->versionPrefix);
        $currentVersion = $configManager->getCurrentVersion();
        $parsed = $versionCalculator->parseVersion($currentVersion);
        $isCurrentPrerelease = null !== $parsed['prerelease'];

        if (empty($changesets) && !$isCurrentPrerelease && null === $prerelease) {
            $io->warning('No changesets found. Nothing to preview.');

            return Command::SUCCESS;
        }

        $nextVersion = $versionCalculator->calculateNextVersion($currentVersion, $changesets, $prerelease);

        $io->title('CHANGELOG Preview');
        $io->text([
            "Current version: <info>{$currentVersion}</info>",
            "Next version: <info>{$nextVersion}</info>",
            '',
        ]);

        if (empty($changesets)) {
            $io->note('No changesets to include in changelog entry.');
            $io->text('The version will be updated but no new changelog content will be added.');

            return Command::SUCCESS;
        }

        $entry = $changelogGenerator->generateEntry($nextVersion, $changesets);

        $io->section('Generated Entry');
        $io->writeln($entry);

        return Command::SUCCESS;
    }
}
