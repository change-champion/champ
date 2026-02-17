<?php

declare(strict_types=1);

namespace ChangeChampion\Services;

use ChangeChampion\Models\Config;

class ConfigManager
{
    private const CHANGESET_DIR = '.changes';
    private const CONFIG_FILE = 'config.json';
    private const README_FILE = 'README.md';

    public function __construct(
        private readonly string $basePath,
    ) {}

    public function getChangesetDir(): string
    {
        return $this->basePath.'/'.self::CHANGESET_DIR;
    }

    public function getConfigPath(): string
    {
        return $this->getChangesetDir().'/'.self::CONFIG_FILE;
    }

    public function isInitialized(): bool
    {
        return is_dir($this->getChangesetDir()) && file_exists($this->getConfigPath());
    }

    public function initialize(?Config $config = null): void
    {
        $config ??= new Config();
        $changesetDir = $this->getChangesetDir();

        if (!is_dir($changesetDir)) {
            mkdir($changesetDir, 0o755, true);
        }

        // Write config file
        file_put_contents(
            $this->getConfigPath(),
            json_encode($config->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        // Write README
        $readme = <<<'README'
            # Changes

            This directory contains changeset files that describe upcoming version changes.

            ## Adding a changeset

            Run `champ add` to create a new changeset file interactively.

            ## Applying changesets

            Run `champ version` to apply all pending changesets and generate changelog entries.

            ## Publishing

            Run `champ publish` to create a git tag for the current version (read from CHANGELOG.md).
            README;

        file_put_contents($changesetDir.'/'.self::README_FILE, $readme."\n");
    }

    public function getConfig(): Config
    {
        if (!$this->isInitialized()) {
            throw new \RuntimeException('Changesets not initialized. Run "champ init" first.');
        }

        $content = file_get_contents($this->getConfigPath());
        $data = json_decode($content, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException('Invalid config.json: '.json_last_error_msg());
        }

        return Config::fromArray($data);
    }

    public function getComposerJsonPath(): string
    {
        return $this->basePath.'/composer.json';
    }

    public function getComposerJson(): array
    {
        $path = $this->getComposerJsonPath();

        if (!file_exists($path)) {
            throw new \RuntimeException('composer.json not found in '.$this->basePath);
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException('Invalid composer.json: '.json_last_error_msg());
        }

        return $data;
    }

    public function saveComposerJson(array $data): void
    {
        file_put_contents(
            $this->getComposerJsonPath(),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    public function getCurrentVersion(): string
    {
        // Get version from git tags (like release-please does for PHP)
        $version = $this->getVersionFromGitTags();

        if (null !== $version) {
            return $version;
        }

        // Fall back to composer.json if no tags exist
        $composerJson = $this->getComposerJson();

        return $composerJson['version'] ?? '0.0.0';
    }

    public function getPackageName(): string
    {
        $composerJson = $this->getComposerJson();

        return $composerJson['name'] ?? 'unknown';
    }

    private function getVersionFromGitTags(): ?string
    {
        // Get the latest semver tag from the project directory
        $output = [];
        $returnCode = 0;

        exec('git -C '.escapeshellarg($this->basePath).' describe --tags --abbrev=0 2>/dev/null', $output, $returnCode);

        if (0 !== $returnCode || empty($output)) {
            return null;
        }

        $tag = trim($output[0]);

        // Strip 'v' prefix if present
        if (str_starts_with($tag, 'v')) {
            $tag = substr($tag, 1);
        }

        // Validate it looks like a semver version (with optional pre-release)
        if (preg_match('/^\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)\.\d+)?$/', $tag)) {
            return $tag;
        }

        return null;
    }
}
