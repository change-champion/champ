<?php

declare(strict_types=1);

namespace ChangeChampion\Models;

class CommitInfo
{
    public function __construct(
        public readonly string $commitHash,
        public readonly string $shortHash,
        public readonly string $subject,
        public readonly ?int $prNumber = null,
    ) {}
}
