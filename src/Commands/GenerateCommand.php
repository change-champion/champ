<?php

declare(strict_types=1);

namespace ChangeChampion\Commands;

use ChangeChampion\Services\ChangesetManager;
use ChangeChampion\Services\ConfigManager;
use ChangeChampion\Services\ConventionalCommitParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'generate',
    description: 'Generate changesets from conventional commits'
)]
class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Starting ref (tag, commit, or branch). Defaults to latest tag.')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Ending ref. Defaults to HEAD.', 'HEAD')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be generated without creating files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $basePath = getcwd();

        $configManager = new ConfigManager($basePath);
        $changesetManager = new ChangesetManager($configManager);
        $parser = new ConventionalCommitParser();

        if (!$configManager->isInitialized()) {
            $io->error('Changesets not initialized. Run "champ init" first.');

            return Command::FAILURE;
        }

        $dryRun = $input->getOption('dry-run');
        $from = $input->getOption('from');
        $to = $input->getOption('to');

        // If no --from, try to find the latest tag
        if (!$from) {
            $from = $this->getLatestTag($basePath);
            if (!$from) {
                $io->warning('No tags found. Will scan all commits on current branch.');
                $from = $this->getFirstCommit($basePath);
                if (!$from) {
                    $io->error('No commits found in repository.');

                    return Command::FAILURE;
                }
            }
        }

        $io->title($dryRun ? 'Generate Changesets (Dry Run)' : 'Generate Changesets');
        $io->text("Scanning commits from <info>{$from}</info> to <info>{$to}</info>");
        $io->newLine();

        // Get commits
        $commits = $this->getCommits($basePath, $from, $to);

        if (empty($commits)) {
            $io->note('No commits found in the specified range.');

            return Command::SUCCESS;
        }

        $io->text(sprintf('Found <info>%d</info> commit(s) to analyze', count($commits)));
        $io->newLine();

        $created = [];
        $skipped = [];

        foreach ($commits as $commit) {
            $parsed = $parser->parse($commit['message']);

            if (!$parsed) {
                $skipped[] = [
                    'hash' => $commit['hash'],
                    'message' => $this->truncate($commit['message'], 60),
                    'reason' => 'Not a conventional commit',
                ];

                continue;
            }

            $changesetType = $parser->getChangesetType($parsed);

            if (!$changesetType) {
                $skipped[] = [
                    'hash' => $commit['hash'],
                    'message' => $this->truncate($commit['message'], 60),
                    'reason' => "Type '{$parsed['type']}' ignored",
                ];

                continue;
            }

            $summary = $parser->formatSummary($parsed);

            if ($dryRun) {
                $created[] = [
                    'type' => $changesetType,
                    'summary' => $summary,
                    'hash' => $commit['hash'],
                ];
            } else {
                $changeset = $changesetManager->create($changesetType, $summary);
                $created[] = [
                    'type' => $changesetType,
                    'summary' => $summary,
                    'hash' => $commit['hash'],
                    'id' => $changeset->id,
                ];
            }
        }

        // Show results
        if (!empty($created)) {
            $io->section($dryRun ? 'Changesets to be created' : 'Created changesets');
            $rows = array_map(fn ($c) => [
                $c['hash'],
                $c['type'],
                $this->truncate($c['summary'], 50),
                $c['id'] ?? '-',
            ], $created);
            $io->table(['Commit', 'Type', 'Summary', 'ID'], $rows);
        }

        if ($output->isVerbose() && !empty($skipped)) {
            $io->section('Skipped commits');
            $rows = array_map(fn ($s) => [
                $s['hash'],
                $this->truncate($s['message'], 40),
                $s['reason'],
            ], $skipped);
            $io->table(['Commit', 'Message', 'Reason'], $rows);
        }

        // Summary
        $io->newLine();
        if ($dryRun) {
            $io->note(sprintf(
                'Would create %d changeset(s), skip %d commit(s).',
                count($created),
                count($skipped)
            ));
        } else {
            if (count($created) > 0) {
                $io->success(sprintf('Created %d changeset(s).', count($created)));
            } else {
                $io->note('No changesets created. All commits were skipped.');
            }
        }

        return Command::SUCCESS;
    }

    private function getLatestTag(string $basePath): ?string
    {
        $output = [];
        $returnCode = 0;

        exec(
            'git -C '.escapeshellarg($basePath).' describe --tags --abbrev=0 2>/dev/null',
            $output,
            $returnCode
        );

        if (0 !== $returnCode || empty($output)) {
            return null;
        }

        return trim($output[0]);
    }

    private function getFirstCommit(string $basePath): ?string
    {
        $output = [];
        $returnCode = 0;

        exec(
            'git -C '.escapeshellarg($basePath).' rev-list --max-parents=0 HEAD 2>/dev/null',
            $output,
            $returnCode
        );

        if (0 !== $returnCode || empty($output)) {
            return null;
        }

        return trim($output[0]);
    }

    /**
     * Get commits between two refs.
     *
     * @return array<array{hash: string, message: string}>
     */
    private function getCommits(string $basePath, string $from, string $to): array
    {
        $output = [];
        $returnCode = 0;

        // Use %x00 as delimiter between commits, %x01 between hash and message
        $format = '%h%x01%B%x00';

        exec(
            sprintf(
                'git -C %s log %s..%s --format=%s 2>/dev/null',
                escapeshellarg($basePath),
                escapeshellarg($from),
                escapeshellarg($to),
                escapeshellarg($format)
            ),
            $output,
            $returnCode
        );

        if (0 !== $returnCode) {
            return [];
        }

        $raw = implode("\n", $output);
        $commits = array_filter(explode("\x00", $raw));

        $result = [];
        foreach ($commits as $commit) {
            $parts = explode("\x01", trim($commit), 2);
            if (2 === count($parts)) {
                $result[] = [
                    'hash' => trim($parts[0]),
                    'message' => trim($parts[1]),
                ];
            }
        }

        return $result;
    }

    private function truncate(string $text, int $length): string
    {
        $firstLine = explode("\n", $text)[0];
        if (strlen($firstLine) <= $length) {
            return $firstLine;
        }

        return substr($firstLine, 0, $length - 3).'...';
    }
}
