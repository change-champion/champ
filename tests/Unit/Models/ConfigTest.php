<?php

declare(strict_types=1);

namespace ChangeChampion\Tests\Unit\Models;

use ChangeChampion\Models\Config;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new Config();

        $this->assertSame('main', $config->baseBranch);
        $this->assertTrue($config->changelog);
        $this->assertSame('release/', $config->releaseBranchPrefix);
        $this->assertSame('v', $config->versionPrefix);
        $this->assertFalse($config->draftRelease);
    }

    public function testCustomValues(): void
    {
        $config = new Config(
            baseBranch: 'develop',
            changelog: false
        );

        $this->assertSame('develop', $config->baseBranch);
        $this->assertFalse($config->changelog);
    }

    public function testFromArrayWithAllValues(): void
    {
        $config = Config::fromArray([
            'baseBranch' => 'master',
            'changelog' => false,
        ]);

        $this->assertSame('master', $config->baseBranch);
        $this->assertFalse($config->changelog);
    }

    public function testFromArrayWithDefaults(): void
    {
        $config = Config::fromArray([]);

        $this->assertSame('main', $config->baseBranch);
        $this->assertTrue($config->changelog);
        $this->assertNull($config->repository);
    }

    public function testFromArrayWithPartialValues(): void
    {
        $config = Config::fromArray([
            'baseBranch' => 'release',
        ]);

        $this->assertSame('release', $config->baseBranch);
        $this->assertTrue($config->changelog);
        $this->assertNull($config->repository);
    }

    public function testFromArrayWithRepository(): void
    {
        $config = Config::fromArray([
            'baseBranch' => 'main',
            'changelog' => true,
            'repository' => 'https://github.com/owner/repo',
        ]);

        $this->assertSame('https://github.com/owner/repo', $config->repository);
    }

    public function testToArray(): void
    {
        $config = new Config(
            baseBranch: 'develop',
            changelog: true
        );

        $array = $config->toArray();

        $this->assertSame([
            'baseBranch' => 'develop',
            'changelog' => true,
            'repository' => null,
            'sections' => Config::DEFAULT_SECTIONS,
            'releaseBranchPrefix' => 'release/',
            'versionPrefix' => 'v',
            'draftRelease' => false,
        ], $array);
    }

    public function testToArrayWithRepository(): void
    {
        $config = new Config(
            baseBranch: 'main',
            changelog: true,
            repository: 'https://github.com/owner/repo'
        );

        $array = $config->toArray();

        $this->assertSame([
            'baseBranch' => 'main',
            'changelog' => true,
            'repository' => 'https://github.com/owner/repo',
            'sections' => Config::DEFAULT_SECTIONS,
            'releaseBranchPrefix' => 'release/',
            'versionPrefix' => 'v',
            'draftRelease' => false,
        ], $array);
    }

    public function testCustomSections(): void
    {
        $customSections = [
            'major' => 'BREAKING CHANGES',
            'minor' => 'Added',
            'patch' => 'Fixed',
        ];

        $config = Config::fromArray([
            'sections' => $customSections,
        ]);

        $this->assertSame($customSections, $config->sections);
        $this->assertSame('BREAKING CHANGES', $config->getSectionHeader('major'));
        $this->assertSame('Added', $config->getSectionHeader('minor'));
        $this->assertSame('Fixed', $config->getSectionHeader('patch'));
    }

    public function testPartialSectionsOverride(): void
    {
        $config = Config::fromArray([
            'sections' => [
                'minor' => 'New Features',
            ],
        ]);

        // Should merge with defaults
        $this->assertSame('Breaking Changes', $config->getSectionHeader('major'));
        $this->assertSame('New Features', $config->getSectionHeader('minor'));
        $this->assertSame('Fixes', $config->getSectionHeader('patch'));
    }

    public function testRoundTrip(): void
    {
        $original = new Config(
            baseBranch: 'feature',
            changelog: false
        );

        $array = $original->toArray();
        $restored = Config::fromArray($array);

        $this->assertSame($original->baseBranch, $restored->baseBranch);
        $this->assertSame($original->changelog, $restored->changelog);
    }

    public function testCustomReleaseBranchPrefix(): void
    {
        $config = Config::fromArray([
            'releaseBranchPrefix' => 'release/',
        ]);

        $this->assertSame('release/', $config->releaseBranchPrefix);
    }

    public function testCustomVersionPrefix(): void
    {
        $config = Config::fromArray([
            'versionPrefix' => 'v',
        ]);

        $this->assertSame('v', $config->versionPrefix);
    }

    public function testDraftRelease(): void
    {
        $config = Config::fromArray([
            'draftRelease' => true,
        ]);

        $this->assertTrue($config->draftRelease);
    }
}
