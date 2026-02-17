<?php

declare(strict_types=1);

namespace ChangeChampion\Commands;

use ChangeChampion\Services\ChangelogGenerator;
use ChangeChampion\Services\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'publish',
    description: 'Create a git tag for the current version'
)]
class PublishCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making changes')
            ->addOption('no-push', null, InputOption::VALUE_NONE, 'Create tag but do not push to remote');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $basePath = getcwd();

        $configManager = new ConfigManager($basePath);

        if (!$configManager->isInitialized()) {
            $io->error('Changesets not initialized. Run "champ init" first.');

            return Command::FAILURE;
        }

        // Check if we're in a git repository
        if (!is_dir($basePath.'/.git')) {
            $io->error('Not a git repository. Please initialize git first.');

            return Command::FAILURE;
        }

        // Get version from CHANGELOG (source of truth after champ version runs)
        $changelogGenerator = new ChangelogGenerator($basePath);
        $version = $changelogGenerator->getLatestVersion();

        if (null === $version) {
            $io->error('No version found in CHANGELOG.md. Run "champ version" first.');

            return Command::FAILURE;
        }

        $packageName = $configManager->getPackageName();
        $tagName = "v{$version}";

        $dryRun = $input->getOption('dry-run');
        $noPush = $input->getOption('no-push');

        $io->title($dryRun ? 'Publish (Dry Run)' : 'Publish');

        $io->text([
            "Package: <info>{$packageName}</info>",
            "Version: <info>{$version}</info>",
            "Tag: <info>{$tagName}</info>",
        ]);

        // Check if tag already exists
        exec('git tag -l '.escapeshellarg($tagName), $existingTags, $exitCode);
        if (!empty($existingTags)) {
            $io->error("Tag {$tagName} already exists.");

            return Command::FAILURE;
        }

        // Check for uncommitted changes
        exec('git status --porcelain', $statusOutput, $exitCode);
        if (!empty($statusOutput)) {
            $io->warning('You have uncommitted changes. Please commit or stash them before publishing.');
            if (!$io->confirm('Continue anyway?', false)) {
                return Command::FAILURE;
            }
        }

        if ($dryRun) {
            $io->newLine();
            $io->note('Dry run mode - no changes will be made.');

            $io->section('Actions that would be performed:');
            $io->listing([
                "Create git tag: {$tagName}",
                $noPush ? 'Skip push (--no-push)' : 'Push tag to remote',
            ]);

            return Command::SUCCESS;
        }

        // Create tag
        $tagMessage = "Release {$version}";
        exec('git tag -a '.escapeshellarg($tagName).' -m '.escapeshellarg($tagMessage).' 2>&1', $tagOutput, $tagExitCode);

        if (0 !== $tagExitCode) {
            $io->error('Failed to create tag: '.implode("\n", $tagOutput));

            return Command::FAILURE;
        }

        $io->text("✓ Created tag {$tagName}");

        // Push tag
        if (!$noPush) {
            $io->text('Pushing tag to remote...');
            exec('git push origin '.escapeshellarg($tagName).' 2>&1', $pushOutput, $pushExitCode);

            if (0 !== $pushExitCode) {
                $io->warning('Failed to push tag: '.implode("\n", $pushOutput));
                $io->text("You can push manually with: git push origin {$tagName}");
            } else {
                $io->text('✓ Pushed tag to origin');
            }
        }

        $io->newLine();
        $io->success([
            "Published {$packageName} {$version}!",
            "Tag: {$tagName}",
        ]);

        if ($noPush) {
            $io->note("Don't forget to push the tag: git push origin {$tagName}");
        }

        return Command::SUCCESS;
    }
}
