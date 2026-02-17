<?php

declare(strict_types=1);

namespace ChangeChampion\Tests\Unit\Models;

use ChangeChampion\Models\Changeset;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ChangesetTest extends TestCase
{
    public function testGetValidTypes(): void
    {
        $types = Changeset::getValidTypes();

        $this->assertContains('major', $types);
        $this->assertContains('minor', $types);
        $this->assertContains('patch', $types);
        $this->assertCount(3, $types);
    }

    public function testFromFileParsesFrontmatter(): void
    {
        $content = <<<'CONTENT'
            ---
            type: minor
            ---

            Add new authentication feature.
            CONTENT;

        $changeset = Changeset::fromFile('/path/to/test-changeset.md', $content);

        $this->assertSame('test-changeset', $changeset->id);
        $this->assertSame('minor', $changeset->type);
        $this->assertSame('Add new authentication feature.', $changeset->summary);
        $this->assertSame('/path/to/test-changeset.md', $changeset->filePath);
    }

    public function testFromFileWithMajorType(): void
    {
        $content = <<<'CONTENT'
            ---
            type: major
            ---

            Breaking change to API.
            CONTENT;

        $changeset = Changeset::fromFile('/path/to/breaking-change.md', $content);

        $this->assertSame('major', $changeset->type);
    }

    public function testFromFileWithPatchType(): void
    {
        $content = <<<'CONTENT'
            ---
            type: patch
            ---

            Fix typo in error message.
            CONTENT;

        $changeset = Changeset::fromFile('/path/to/fix.md', $content);

        $this->assertSame('patch', $changeset->type);
    }

    public function testFromFileWithMultilineSummary(): void
    {
        $content = <<<'CONTENT'
            ---
            type: minor
            ---

            Add new feature.

            This is a longer description that spans
            multiple lines and provides more context.
            CONTENT;

        $changeset = Changeset::fromFile('/path/to/feature.md', $content);

        $this->assertStringContainsString('Add new feature.', $changeset->summary);
        $this->assertStringContainsString('multiple lines', $changeset->summary);
    }

    public function testFromFileThrowsOnInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid changeset format');

        Changeset::fromFile('/path/to/invalid.md', 'No frontmatter here');
    }

    public function testFromFileThrowsOnMissingType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing 'type'");

        $content = <<<'CONTENT'
            ---
            foo: bar
            ---

            Some content.
            CONTENT;

        Changeset::fromFile('/path/to/no-type.md', $content);
    }

    public function testFromFileThrowsOnInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 'invalid'");

        $content = <<<'CONTENT'
            ---
            type: invalid
            ---

            Some content.
            CONTENT;

        Changeset::fromFile('/path/to/invalid-type.md', $content);
    }

    public function testToFileContent(): void
    {
        $changeset = new Changeset(
            id: 'test-id',
            type: 'minor',
            summary: 'Test summary',
            filePath: '/path/to/test.md'
        );

        $content = $changeset->toFileContent();

        $this->assertStringContainsString('---', $content);
        $this->assertStringContainsString('type: minor', $content);
        $this->assertStringContainsString('Test summary', $content);
    }

    public function testToFileContentRoundTrip(): void
    {
        $original = new Changeset(
            id: 'round-trip',
            type: 'patch',
            summary: 'Fix bug in parser',
            filePath: '/path/to/round-trip.md'
        );

        $content = $original->toFileContent();
        $parsed = Changeset::fromFile('/path/to/round-trip.md', $content);

        $this->assertSame($original->type, $parsed->type);
        $this->assertSame($original->summary, $parsed->summary);
    }
}
