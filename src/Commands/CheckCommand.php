<?php

declare(strict_types=1);

namespace ChangeChampion\Commands;

use ChangeChampion\Models\Changeset;
use ChangeChampion\Services\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'check',
    description: 'Validate changeset files for correct format'
)]
class CheckCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $basePath = getcwd();

        $configManager = new ConfigManager($basePath);

        if (!$configManager->isInitialized()) {
            $io->error('Changesets not initialized. Run "champ init" first.');

            return Command::FAILURE;
        }

        $changesetDir = $configManager->getChangesetDir();

        $finder = new Finder();
        $finder->files()
            ->in($changesetDir)
            ->name('*.md')
            ->notName('README.md');

        $errors = [];
        $valid = 0;

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $fileName = $file->getFilename();

            try {
                $content = file_get_contents($filePath);
                $changeset = Changeset::fromFile($filePath, $content);

                // Additional validation: summary should not be empty
                if (empty(trim($changeset->summary))) {
                    $errors[] = [
                        'file' => $fileName,
                        'error' => 'Changeset summary is empty',
                    ];
                } else {
                    ++$valid;
                }
            } catch (\InvalidArgumentException $e) {
                $errors[] = [
                    'file' => $fileName,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $total = $valid + count($errors);

        if (0 === $total) {
            $io->note('No changeset files found.');

            return Command::SUCCESS;
        }

        if (empty($errors)) {
            $io->success("All {$valid} changeset(s) are valid.");

            return Command::SUCCESS;
        }

        $io->error('Found '.count($errors).' invalid changeset(s):');

        foreach ($errors as $error) {
            $io->writeln("  <fg=red>✗</> {$error['file']}: {$error['error']}");
        }

        if ($valid > 0) {
            $io->newLine();
            $io->writeln("<info>{$valid} changeset(s) valid, ".count($errors).' invalid.</info>');
        }

        return Command::FAILURE;
    }
}
