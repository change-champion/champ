<?php

declare(strict_types=1);

namespace ChangeChampion\Models;

class Changeset
{
    public const TYPE_MAJOR = 'major';
    public const TYPE_MINOR = 'minor';
    public const TYPE_PATCH = 'patch';

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $summary,
        public readonly string $filePath,
    ) {}

    public static function getValidTypes(): array
    {
        return [
            self::TYPE_MAJOR,
            self::TYPE_MINOR,
            self::TYPE_PATCH,
        ];
    }

    public static function fromFile(string $filePath, string $content): self
    {
        $id = pathinfo($filePath, PATHINFO_FILENAME);

        // Parse frontmatter
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            throw new \InvalidArgumentException("Invalid changeset format in {$filePath}");
        }

        $frontmatter = $matches[1];
        $summary = trim($matches[2]);

        // Parse type from frontmatter
        if (!preg_match('/type:\s*(\w+)/', $frontmatter, $typeMatch)) {
            throw new \InvalidArgumentException("Missing 'type' in changeset {$filePath}");
        }

        $type = $typeMatch[1];

        if (!in_array($type, self::getValidTypes(), true)) {
            throw new \InvalidArgumentException("Invalid type '{$type}' in changeset {$filePath}");
        }

        return new self(
            id: $id,
            type: $type,
            summary: $summary,
            filePath: $filePath,
        );
    }

    public function toFileContent(): string
    {
        return <<<CONTENT
            ---
            type: {$this->type}
            ---

            {$this->summary}
            CONTENT;
    }
}
