<?php

declare(strict_types=1);

namespace ChangeChampion\Tests\Unit\Services;

use ChangeChampion\Models\Changeset;
use ChangeChampion\Services\ConventionalCommitParser;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ConventionalCommitParserTest extends TestCase
{
    private ConventionalCommitParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ConventionalCommitParser();
    }

    public function testParseSimpleFeat(): void
    {
        $result = $this->parser->parse('feat: add new feature');

        $this->assertNotNull($result);
        $this->assertSame('feat', $result['type']);
        $this->assertNull($result['scope']);
        $this->assertFalse($result['breaking']);
        $this->assertSame('add new feature', $result['description']);
    }

    public function testParseFeatWithScope(): void
    {
        $result = $this->parser->parse('feat(auth): add OAuth support');

        $this->assertNotNull($result);
        $this->assertSame('feat', $result['type']);
        $this->assertSame('auth', $result['scope']);
        $this->assertFalse($result['breaking']);
        $this->assertSame('add OAuth support', $result['description']);
    }

    public function testParseBreakingWithBang(): void
    {
        $result = $this->parser->parse('feat!: remove deprecated API');

        $this->assertNotNull($result);
        $this->assertSame('feat', $result['type']);
        $this->assertTrue($result['breaking']);
        $this->assertSame('remove deprecated API', $result['description']);
    }

    public function testParseBreakingWithScopeAndBang(): void
    {
        $result = $this->parser->parse('feat(api)!: change response format');

        $this->assertNotNull($result);
        $this->assertSame('feat', $result['type']);
        $this->assertSame('api', $result['scope']);
        $this->assertTrue($result['breaking']);
    }

    public function testParseBreakingInBody(): void
    {
        $message = "feat: update authentication\n\nBREAKING CHANGE: tokens now expire after 1 hour";
        $result = $this->parser->parse($message);

        $this->assertNotNull($result);
        $this->assertTrue($result['breaking']);
        $this->assertStringContains('tokens now expire', $result['body']);
    }

    public function testParseFix(): void
    {
        $result = $this->parser->parse('fix: resolve null pointer exception');

        $this->assertNotNull($result);
        $this->assertSame('fix', $result['type']);
        $this->assertSame('resolve null pointer exception', $result['description']);
    }

    public function testParseNonConventional(): void
    {
        $result = $this->parser->parse('Update README');

        $this->assertNull($result);
    }

    public function testParseEmptyMessage(): void
    {
        $result = $this->parser->parse('');

        $this->assertNull($result);
    }

    public function testGetChangesetTypeFeat(): void
    {
        $parsed = $this->parser->parse('feat: add feature');
        $type = $this->parser->getChangesetType($parsed);

        $this->assertSame(Changeset::TYPE_MINOR, $type);
    }

    public function testGetChangesetTypeFix(): void
    {
        $parsed = $this->parser->parse('fix: fix bug');
        $type = $this->parser->getChangesetType($parsed);

        $this->assertSame(Changeset::TYPE_PATCH, $type);
    }

    public function testGetChangesetTypeBreaking(): void
    {
        $parsed = $this->parser->parse('feat!: breaking change');
        $type = $this->parser->getChangesetType($parsed);

        $this->assertSame(Changeset::TYPE_MAJOR, $type);
    }

    public function testGetChangesetTypeChore(): void
    {
        $parsed = $this->parser->parse('chore: update deps');
        $type = $this->parser->getChangesetType($parsed);

        $this->assertNull($type);
    }

    public function testGetChangesetTypeDocs(): void
    {
        $parsed = $this->parser->parse('docs: update readme');
        $type = $this->parser->getChangesetType($parsed);

        $this->assertNull($type);
    }

    public function testGetChangesetTypeTest(): void
    {
        $parsed = $this->parser->parse('test: add unit tests');
        $type = $this->parser->getChangesetType($parsed);

        $this->assertNull($type);
    }

    public function testGetChangesetTypePerf(): void
    {
        $parsed = $this->parser->parse('perf: improve query performance');
        $type = $this->parser->getChangesetType($parsed);

        $this->assertSame(Changeset::TYPE_PATCH, $type);
    }

    public function testFormatSummarySimple(): void
    {
        $parsed = $this->parser->parse('feat: add new feature');
        $summary = $this->parser->formatSummary($parsed);

        $this->assertSame('add new feature', $summary);
    }

    public function testFormatSummaryWithScope(): void
    {
        $parsed = $this->parser->parse('feat(auth): add OAuth support');
        $summary = $this->parser->formatSummary($parsed);

        $this->assertSame('**auth**: add OAuth support', $summary);
    }

    public function testFormatSummaryIncludesIssueFromFooter(): void
    {
        $parsed = $this->parser->parse("fix: resolve null pointer\n\nFixes #45");
        $summary = $this->parser->formatSummary($parsed);

        $this->assertSame('resolve null pointer (#45)', $summary);
    }

    public function testFormatSummaryIncludesMultipleIssuesFromFooter(): void
    {
        $parsed = $this->parser->parse("fix: resolve bugs\n\nFixes #10\nCloses #20");
        $summary = $this->parser->formatSummary($parsed);

        $this->assertStringContainsString('(#10)', $summary);
        $this->assertStringContainsString('(#20)', $summary);
    }

    public function testFormatSummaryDoesNotDuplicateIssueAlreadyInDescription(): void
    {
        $parsed = $this->parser->parse("fix: resolve null pointer (#45)\n\nFixes #45");
        $summary = $this->parser->formatSummary($parsed);

        $this->assertSame('resolve null pointer (#45)', $summary);
    }

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertStringContainsString($needle, $haystack);
    }
}
