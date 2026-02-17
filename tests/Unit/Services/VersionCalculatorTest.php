<?php

declare(strict_types=1);

namespace ChangeChampion\Tests\Unit\Services;

use ChangeChampion\Models\Changeset;
use ChangeChampion\Services\VersionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class VersionCalculatorTest extends TestCase
{
    private VersionCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new VersionCalculator();
    }

    public function testBumpVersionPatch(): void
    {
        $this->assertSame('1.2.4', $this->calculator->bumpVersion('1.2.3', 'patch'));
    }

    public function testBumpVersionMinor(): void
    {
        $this->assertSame('1.3.0', $this->calculator->bumpVersion('1.2.3', 'minor'));
    }

    public function testBumpVersionMajor(): void
    {
        $this->assertSame('2.0.0', $this->calculator->bumpVersion('1.2.3', 'major'));
    }

    public function testBumpVersionWithVPrefix(): void
    {
        $this->assertSame('1.2.4', $this->calculator->bumpVersion('v1.2.3', 'patch'));
    }

    public function testBumpVersionWithCapitalVPrefix(): void
    {
        $this->assertSame('1.2.4', $this->calculator->bumpVersion('V1.2.3', 'patch'));
    }

    public function testBumpVersionFromZero(): void
    {
        $this->assertSame('0.0.1', $this->calculator->bumpVersion('0.0.0', 'patch'));
        $this->assertSame('0.1.0', $this->calculator->bumpVersion('0.0.0', 'minor'));
        $this->assertSame('1.0.0', $this->calculator->bumpVersion('0.0.0', 'major'));
    }

    public function testBumpVersionWithTwoPartVersion(): void
    {
        $this->assertSame('1.3.0', $this->calculator->bumpVersion('1.2', 'minor'));
    }

    public function testBumpVersionWithOnePartVersion(): void
    {
        $this->assertSame('2.0.0', $this->calculator->bumpVersion('1', 'major'));
    }

    public function testGetHighestBumpTypeWithMajor(): void
    {
        $changesets = [
            $this->createChangeset('patch'),
            $this->createChangeset('major'),
            $this->createChangeset('minor'),
        ];

        $this->assertSame('major', $this->calculator->getHighestBumpType($changesets));
    }

    public function testGetHighestBumpTypeWithMinor(): void
    {
        $changesets = [
            $this->createChangeset('patch'),
            $this->createChangeset('minor'),
            $this->createChangeset('patch'),
        ];

        $this->assertSame('minor', $this->calculator->getHighestBumpType($changesets));
    }

    public function testGetHighestBumpTypeWithOnlyPatch(): void
    {
        $changesets = [
            $this->createChangeset('patch'),
            $this->createChangeset('patch'),
        ];

        $this->assertSame('patch', $this->calculator->getHighestBumpType($changesets));
    }

    public function testGetHighestBumpTypeWithSingleChangeset(): void
    {
        $changesets = [$this->createChangeset('minor')];

        $this->assertSame('minor', $this->calculator->getHighestBumpType($changesets));
    }

    public function testCalculateNextVersionWithNoChangesets(): void
    {
        $this->assertSame('1.2.3', $this->calculator->calculateNextVersion('1.2.3', []));
    }

    public function testCalculateNextVersionWithPatchChangesets(): void
    {
        $changesets = [
            $this->createChangeset('patch'),
            $this->createChangeset('patch'),
        ];

        $this->assertSame('1.2.4', $this->calculator->calculateNextVersion('1.2.3', $changesets));
    }

    public function testCalculateNextVersionWithMixedChangesets(): void
    {
        $changesets = [
            $this->createChangeset('patch'),
            $this->createChangeset('minor'),
            $this->createChangeset('patch'),
        ];

        $this->assertSame('1.3.0', $this->calculator->calculateNextVersion('1.2.3', $changesets));
    }

    public function testCalculateNextVersionWithMajorChangeset(): void
    {
        $changesets = [
            $this->createChangeset('patch'),
            $this->createChangeset('major'),
        ];

        $this->assertSame('2.0.0', $this->calculator->calculateNextVersion('1.2.3', $changesets));
    }

    // Pre-release tests

    public function testParseVersionStable(): void
    {
        $parsed = $this->calculator->parseVersion('1.2.3');

        $this->assertSame(1, $parsed['major']);
        $this->assertSame(2, $parsed['minor']);
        $this->assertSame(3, $parsed['patch']);
        $this->assertNull($parsed['prerelease']);
        $this->assertNull($parsed['prereleaseNum']);
    }

    public function testParseVersionWithPrerelease(): void
    {
        $parsed = $this->calculator->parseVersion('1.2.3-alpha.5');

        $this->assertSame(1, $parsed['major']);
        $this->assertSame(2, $parsed['minor']);
        $this->assertSame(3, $parsed['patch']);
        $this->assertSame('alpha', $parsed['prerelease']);
        $this->assertSame(5, $parsed['prereleaseNum']);
    }

    public function testParseVersionWithVPrefixAndPrerelease(): void
    {
        $parsed = $this->calculator->parseVersion('v2.0.0-rc.1');

        $this->assertSame(2, $parsed['major']);
        $this->assertSame(0, $parsed['minor']);
        $this->assertSame(0, $parsed['patch']);
        $this->assertSame('rc', $parsed['prerelease']);
        $this->assertSame(1, $parsed['prereleaseNum']);
    }

    public function testCreateAlphaFromStable(): void
    {
        $changesets = [$this->createChangeset('minor')];

        // 1.0.0 with minor changeset + alpha = 1.1.0-alpha.1
        $result = $this->calculator->calculateNextVersion('1.0.0', $changesets, 'alpha');

        $this->assertSame('1.1.0-alpha.1', $result);
    }

    public function testCreateBetaFromStable(): void
    {
        $changesets = [$this->createChangeset('major')];

        // 1.2.3 with major changeset + beta = 2.0.0-beta.1
        $result = $this->calculator->calculateNextVersion('1.2.3', $changesets, 'beta');

        $this->assertSame('2.0.0-beta.1', $result);
    }

    public function testBumpAlpha(): void
    {
        // 1.1.0-alpha.1 + alpha = 1.1.0-alpha.2
        $result = $this->calculator->calculateNextVersion('1.1.0-alpha.1', [], 'alpha');

        $this->assertSame('1.1.0-alpha.2', $result);
    }

    public function testBumpAlphaMultipleTimes(): void
    {
        // 1.1.0-alpha.5 + alpha = 1.1.0-alpha.6
        $result = $this->calculator->calculateNextVersion('1.1.0-alpha.5', [], 'alpha');

        $this->assertSame('1.1.0-alpha.6', $result);
    }

    public function testAlphaToBeta(): void
    {
        // 1.1.0-alpha.3 + beta = 1.1.0-beta.1
        $result = $this->calculator->calculateNextVersion('1.1.0-alpha.3', [], 'beta');

        $this->assertSame('1.1.0-beta.1', $result);
    }

    public function testBetaToRc(): void
    {
        // 1.1.0-beta.2 + rc = 1.1.0-rc.1
        $result = $this->calculator->calculateNextVersion('1.1.0-beta.2', [], 'rc');

        $this->assertSame('1.1.0-rc.1', $result);
    }

    public function testRcToStable(): void
    {
        // 1.1.0-rc.1 + no prerelease = 1.1.0 (graduate to stable)
        $result = $this->calculator->calculateNextVersion('1.1.0-rc.1', [], null);

        $this->assertSame('1.1.0', $result);
    }

    public function testAlphaToStable(): void
    {
        // 2.0.0-alpha.5 + no prerelease = 2.0.0 (graduate to stable)
        $result = $this->calculator->calculateNextVersion('2.0.0-alpha.5', [], null);

        $this->assertSame('2.0.0', $result);
    }

    public function testPrereleaseWithChangesetsIgnoresChangesets(): void
    {
        // When on a prerelease and bumping prerelease, changesets don't affect the base version
        $changesets = [$this->createChangeset('major')];

        // 1.1.0-alpha.1 + major changeset + alpha = 1.1.0-alpha.2 (not 2.0.0-alpha.1)
        $result = $this->calculator->calculateNextVersion('1.1.0-alpha.1', $changesets, 'alpha');

        $this->assertSame('1.1.0-alpha.2', $result);
    }

    private function createChangeset(string $type): Changeset
    {
        return new Changeset(
            id: 'test-'.uniqid(),
            type: $type,
            summary: 'Test changeset',
            filePath: '/tmp/test.md'
        );
    }
}
