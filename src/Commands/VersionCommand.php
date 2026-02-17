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
    name: 'version',
    description: 'Apply changesets, bump version, and update changelog'
)]
class VersionCommand extends Command
{
    private const VALID_PRERELEASES = ['alpha', 'beta', 'rc'];

    protected function configure(): void
    {
        $this
            ->addOption('prerelease', 'p', InputOption::VALUE_REQUIRED, 'Create pre-release version (alpha, beta, rc)')
            ->addOption('no-changelog', null, InputOption::VALUE_NONE, 'Skip changelog generation')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making changes');
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

        // Allow no changesets if we're bumping pre-release or graduating to stable
        if (empty($changesets) && !$isCurrentPrerelease && null === $prerelease) {
            $io->warning('No changesets found. Nothing to do.');

            return Command::SUCCESS;
        }

        $nextVersion = $versionCalculator->calculateNextVersion($currentVersion, $changesets, $prerelease);
        $packageName = $configManager->getPackageName();

        // Determine bump description
        if (null !== $prerelease) {
            if ($isCurrentPrerelease || empty($changesets)) {
                $bumpType = "pre-release ({$prerelease})";
            } else {
                $bumpType = "{$versionCalculator->getHighestBumpType($changesets)} + {$prerelease}";
            }
        } elseif ($isCurrentPrerelease) {
            $bumpType = 'stable release';
        } else {
            $bumpType = $versionCalculator->getHighestBumpType($changesets);
        }

        $dryRun = $input->getOption('dry-run');
        $skipChangelog = $input->getOption('no-changelog') || !$config->changelog;

        $io->title($dryRun ? 'Version (Dry Run)' : 'Version');

        $io->text([
            "Package: <info>{$packageName}</info>",
            "Version bump: <info>{$currentVersion}</info> → <info>{$nextVersion}</info> ({$bumpType})",
            'Changesets to apply: <info>'.count($changesets).'</info>',
        ]);

        if ($dryRun) {
            $io->newLine();
            $io->note('Dry run mode - no changes will be made.');

            $io->section('Changes that would be applied:');
            $io->listing([
                $skipChangelog ? 'Skip changelog (disabled)' : "Update CHANGELOG.md with {$nextVersion} section",
                'Delete '.count($changesets).' changeset file(s)',
            ]);

            return Command::SUCCESS;
        }

        // Confirm
        if (!$io->confirm("Apply {$bumpType} bump to version {$nextVersion}?", true)) {
            $io->warning('Aborted.');

            return Command::SUCCESS;
        }

        // Update changelog
        if (!$skipChangelog) {
            $changelogGenerator->update($nextVersion, $changesets);
            $io->text('✓ Updated CHANGELOG.md');
        }

        // Delete changesets
        $changesetManager->deleteAll();
        $io->text('✓ Deleted '.count($changesets).' changeset file(s)');

        $io->newLine();
        $io->success([
            "Version {$nextVersion} is ready!",
            'Next steps:',
            '  1. Review the changes to CHANGELOG.md',
            '  2. Commit the changes',
            '  3. Run "champ publish" to create a git tag (v'.$nextVersion.')',
        ]);

        return Command::SUCCESS;
    }
}
