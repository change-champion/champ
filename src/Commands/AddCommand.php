<?php

declare(strict_types=1);

namespace ChangeChampion\Commands;

use ChangeChampion\Models\Changeset;
use ChangeChampion\Services\ChangesetManager;
use ChangeChampion\Services\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'add',
    description: 'Create a new changeset'
)]
class AddCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('empty', null, InputOption::VALUE_NONE, 'Create an empty changeset (for CI)')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Bump type (major, minor, patch)')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Changeset summary message');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $basePath = getcwd();

        $configManager = new ConfigManager($basePath);
        $changesetManager = new ChangesetManager($configManager);

        if (!$configManager->isInitialized()) {
            $io->error('Changesets not initialized. Run "champ init" first.');

            return Command::FAILURE;
        }

        // Handle empty changeset
        if ($input->getOption('empty')) {
            $changeset = $changesetManager->create(Changeset::TYPE_PATCH, '');
            $io->success("Created empty changeset: {$changeset->id}");

            return Command::SUCCESS;
        }

        // Get bump type
        $type = $input->getOption('type');
        if (!$type) {
            $type = $io->choice(
                'What kind of change is this?',
                [
                    Changeset::TYPE_PATCH => 'patch - Bug fixes, minor changes (0.0.x)',
                    Changeset::TYPE_MINOR => 'minor - New features, backwards compatible (0.x.0)',
                    Changeset::TYPE_MAJOR => 'major - Breaking changes (x.0.0)',
                ],
                Changeset::TYPE_PATCH
            );
            // Extract type from the choice key
            $type = explode(' ', $type)[0];
        }

        if (!in_array($type, Changeset::getValidTypes(), true)) {
            $io->error("Invalid type: {$type}. Must be one of: ".implode(', ', Changeset::getValidTypes()));

            return Command::FAILURE;
        }

        // Get summary
        $summary = $input->getOption('message');
        if (!$summary) {
            $summary = $io->ask(
                'Please enter a summary for this change (this will appear in the changelog)',
                null,
                function ($value) {
                    if (empty(trim($value))) {
                        throw new \RuntimeException('Summary cannot be empty.');
                    }

                    return $value;
                }
            );
        }

        // Create changeset
        $changeset = $changesetManager->create($type, $summary);

        $io->success([
            "Created changeset: {$changeset->id}",
            "Type: {$changeset->type}",
            "File: {$changeset->filePath}",
        ]);

        return Command::SUCCESS;
    }
}
