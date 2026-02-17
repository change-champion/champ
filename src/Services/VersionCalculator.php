<?php

declare(strict_types=1);

namespace ChangeChampion\Services;

use ChangeChampion\Models\Changeset;

class VersionCalculator
{
    private const PRERELEASE_ORDER = ['alpha' => 1, 'beta' => 2, 'rc' => 3];

    /**
     * Calculate the next version based on changesets.
     *
     * @param Changeset[] $changesets
     * @param null|string $prerelease Pre-release type: 'alpha', 'beta', or 'rc'
     */
    public function calculateNextVersion(string $currentVersion, array $changesets, ?string $prerelease = null): string
    {
        $parsed = $this->parseVersion($currentVersion);

        // If graduating from pre-release to stable (no prerelease flag, but current has prerelease)
        if (null === $prerelease && null !== $parsed['prerelease']) {
            // If there are changesets, apply them starting from the stable base
            return sprintf('%d.%d.%d', $parsed['major'], $parsed['minor'], $parsed['patch']);
        }

        // If no changesets and no prerelease change, return current
        if (empty($changesets) && null === $prerelease) {
            return $currentVersion;
        }

        // If currently on a pre-release and requesting same or later pre-release type
        if (null !== $parsed['prerelease'] && null !== $prerelease) {
            return $this->bumpPrerelease(
                $parsed['major'],
                $parsed['minor'],
                $parsed['patch'],
                $parsed['prerelease'],
                $parsed['prereleaseNum'],
                $prerelease
            );
        }

        // Calculate base version from changesets
        if (!empty($changesets)) {
            $bumpType = $this->getHighestBumpType($changesets);
            $baseVersion = $this->bumpVersion($currentVersion, $bumpType);
        } else {
            $baseVersion = sprintf('%d.%d.%d', $parsed['major'], $parsed['minor'], $parsed['patch']);
        }

        // If prerelease requested, append it
        if (null !== $prerelease) {
            return $baseVersion.'-'.$prerelease.'.1';
        }

        return $baseVersion;
    }

    /**
     * Parse a version string into components.
     *
     * @return array{major: int, minor: int, patch: int, prerelease: null|string, prereleaseNum: null|int}
     */
    public function parseVersion(string $version): array
    {
        // Remove 'v' prefix if present
        $version = ltrim($version, 'vV');

        // Match version with optional pre-release: 1.2.3 or 1.2.3-alpha.1
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-(alpha|beta|rc)\.(\d+))?$/', $version, $matches)) {
            return [
                'major' => (int) $matches[1],
                'minor' => (int) $matches[2],
                'patch' => (int) $matches[3],
                'prerelease' => $matches[4] ?? null,
                'prereleaseNum' => isset($matches[5]) ? (int) $matches[5] : null,
            ];
        }

        // Fallback: parse basic version
        $parts = explode('.', explode('-', $version)[0]);
        while (count($parts) < 3) {
            $parts[] = '0';
        }

        return [
            'major' => (int) $parts[0],
            'minor' => (int) $parts[1],
            'patch' => (int) $parts[2],
            'prerelease' => null,
            'prereleaseNum' => null,
        ];
    }

    /**
     * Get the highest priority bump type from changesets.
     * Priority: major > minor > patch.
     *
     * @param Changeset[] $changesets
     */
    public function getHighestBumpType(array $changesets): string
    {
        $hasMajor = false;
        $hasMinor = false;

        foreach ($changesets as $changeset) {
            if (Changeset::TYPE_MAJOR === $changeset->type) {
                $hasMajor = true;

                break; // Can't get higher than major
            }
            if (Changeset::TYPE_MINOR === $changeset->type) {
                $hasMinor = true;
            }
        }

        if ($hasMajor) {
            return Changeset::TYPE_MAJOR;
        }

        if ($hasMinor) {
            return Changeset::TYPE_MINOR;
        }

        return Changeset::TYPE_PATCH;
    }

    /**
     * Bump a version by the specified type.
     */
    public function bumpVersion(string $version, string $type): string
    {
        // Remove 'v' prefix if present
        $version = ltrim($version, 'vV');

        // Parse the version
        $parts = explode('.', $version);

        // Ensure we have at least 3 parts
        while (count($parts) < 3) {
            $parts[] = '0';
        }

        $major = (int) $parts[0];
        $minor = (int) $parts[1];
        $patch = (int) $parts[2];

        switch ($type) {
            case Changeset::TYPE_MAJOR:
                $major++;
                $minor = 0;
                $patch = 0;

                break;

            case Changeset::TYPE_MINOR:
                $minor++;
                $patch = 0;

                break;

            case Changeset::TYPE_PATCH:
            default:
                $patch++;

                break;
        }

        return sprintf('%d.%d.%d', $major, $minor, $patch);
    }

    /**
     * Bump pre-release version.
     */
    private function bumpPrerelease(
        int $major,
        int $minor,
        int $patch,
        string $currentPrerelease,
        int $currentNum,
        string $targetPrerelease
    ): string {
        $baseVersion = sprintf('%d.%d.%d', $major, $minor, $patch);
        $currentOrder = self::PRERELEASE_ORDER[$currentPrerelease] ?? 0;
        $targetOrder = self::PRERELEASE_ORDER[$targetPrerelease] ?? 0;

        if ($targetOrder > $currentOrder) {
            // Moving to later pre-release stage (alpha -> beta -> rc)
            return $baseVersion.'-'.$targetPrerelease.'.1';
        }

        if ($targetOrder === $currentOrder) {
            // Same pre-release type, increment number
            return $baseVersion.'-'.$targetPrerelease.'.'.($currentNum + 1);
        }

        // Going backwards (e.g., rc -> alpha) - start new cycle
        return $baseVersion.'-'.$targetPrerelease.'.1';
    }
}
