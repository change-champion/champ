<?php

declare(strict_types=1);

namespace ChangeChampion\Services;

use ChangeChampion\Models\Changeset;
use Symfony\Component\Finder\Finder;

class ChangesetManager
{
    public function __construct(
        private readonly ConfigManager $configManager,
    ) {}

    public function create(string $type, string $summary): Changeset
    {
        $id = $this->generateId();
        $filePath = $this->configManager->getChangesetDir().'/'.$id.'.md';

        $changeset = new Changeset(
            id: $id,
            type: $type,
            summary: $summary,
            filePath: $filePath,
        );

        file_put_contents($filePath, $changeset->toFileContent()."\n");

        return $changeset;
    }

    /**
     * @return Changeset[]
     */
    public function getAll(): array
    {
        $changesetDir = $this->configManager->getChangesetDir();

        if (!is_dir($changesetDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()
            ->in($changesetDir)
            ->name('*.md')
            ->notName('README.md');

        $changesets = [];

        foreach ($finder as $file) {
            try {
                $changesets[] = Changeset::fromFile(
                    $file->getPathname(),
                    $file->getContents()
                );
            } catch (\InvalidArgumentException $e) {
                // Skip invalid changeset files
                continue;
            }
        }

        return $changesets;
    }

    public function delete(Changeset $changeset): void
    {
        if (file_exists($changeset->filePath)) {
            unlink($changeset->filePath);
        }
    }

    public function deleteAll(): void
    {
        foreach ($this->getAll() as $changeset) {
            $this->delete($changeset);
        }
    }

    private function generateId(): string
    {
        // Generate a random adjective-noun-adjective style ID like changesets does
        $adjectives = [
            'brave', 'calm', 'eager', 'fair', 'gentle', 'happy', 'jolly', 'kind',
            'lively', 'merry', 'nice', 'proud', 'quick', 'smart', 'witty', 'young',
            'bright', 'clean', 'fresh', 'light', 'neat', 'sharp', 'soft', 'warm',
            'cool', 'fast', 'loud', 'quiet', 'rich', 'safe', 'tall', 'wise',
        ];

        $nouns = [
            'apple', 'bird', 'cloud', 'dream', 'eagle', 'flame', 'grape', 'house',
            'island', 'jewel', 'kite', 'lake', 'moon', 'nest', 'ocean', 'pearl',
            'river', 'star', 'tree', 'wave', 'berry', 'cedar', 'daisy', 'frost',
            'grass', 'honey', 'ivory', 'jade', 'lemon', 'maple', 'olive', 'peach',
        ];

        return sprintf(
            '%s-%s-%s',
            $adjectives[array_rand($adjectives)],
            $nouns[array_rand($nouns)],
            $adjectives[array_rand($adjectives)]
        );
    }
}
