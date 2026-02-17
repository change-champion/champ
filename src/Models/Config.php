<?php

declare(strict_types=1);

namespace ChangeChampion\Models;

class Config
{
    public const DEFAULT_SECTIONS = [
        'major' => 'Breaking Changes',
        'minor' => 'Features',
        'patch' => 'Fixes',
    ];

    public function __construct(
        public readonly string $baseBranch = 'main',
        public readonly bool $changelog = true,
        public readonly ?string $repository = null,
        public readonly array $sections = self::DEFAULT_SECTIONS,
        public readonly string $releaseBranchPrefix = 'release/',
        public readonly string $versionPrefix = 'v',
        public readonly bool $draftRelease = false,
    ) {}

    public static function fromArray(array $data): self
    {
        $sections = array_merge(self::DEFAULT_SECTIONS, $data['sections'] ?? []);

        return new self(
            baseBranch: $data['baseBranch'] ?? 'main',
            changelog: $data['changelog'] ?? true,
            repository: $data['repository'] ?? null,
            sections: $sections,
            releaseBranchPrefix: $data['releaseBranchPrefix'] ?? 'release/',
            versionPrefix: $data['versionPrefix'] ?? 'v',
            draftRelease: $data['draftRelease'] ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'baseBranch' => $this->baseBranch,
            'changelog' => $this->changelog,
            'repository' => $this->repository,
            'sections' => $this->sections,
            'releaseBranchPrefix' => $this->releaseBranchPrefix,
            'versionPrefix' => $this->versionPrefix,
            'draftRelease' => $this->draftRelease,
        ];
    }

    public function getSectionHeader(string $type): string
    {
        return $this->sections[$type] ?? ucfirst($type);
    }
}
