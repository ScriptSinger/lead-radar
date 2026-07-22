<?php

namespace Tests\Unit;

use App\Services\Vk\LeadMatcher;
use PHPUnit\Framework\TestCase;

class LeadMatcherTest extends TestCase
{
    private LeadMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new LeadMatcher;
    }

    public function test_normalize_lowercases_and_replaces_yo(): void
    {
        $this->assertSame('елка работа', $this->matcher->normalize('  Ёлка   Работа '));
    }

    public function test_normalize_collapses_whitespace_and_trim(): void
    {
        $this->assertSame('ремонт холодильника', $this->matcher->normalize("  ремонт\t\nхолодильника  "));
        $this->assertSame('', $this->matcher->normalize('   '));
    }

    public function test_normalize_is_case_insensitive_cyrillic(): void
    {
        $this->assertSame(
            $this->matcher->normalize('СТИРАЛЬНАЯ МАШИНА'),
            $this->matcher->normalize('стиральная машина'),
        );
    }

    public function test_matches_substring_case_insensitive(): void
    {
        $this->assertTrue($this->matcher->matches('Требуется помощник', 'требуется'));
        $this->assertTrue($this->matcher->matches('Ищу ПОДРАБОТКУ', 'подработк'));
        $this->assertTrue($this->matcher->matches('Ищу подработку', 'подработку'));
        $this->assertFalse($this->matcher->matches('Привет всем', 'работа'));
    }

    public function test_matches_with_yo_equivalence(): void
    {
        $this->assertTrue($this->matcher->matches('Нужен Ёлочный сервис', 'елочн'));
        $this->assertTrue($this->matcher->matches('елка', 'Ёлка'));
    }

    public function test_matches_rejects_empty_needle_or_haystack(): void
    {
        $this->assertFalse($this->matcher->matches('текст', ''));
        $this->assertFalse($this->matcher->matches('', 'слово'));
        $this->assertFalse($this->matcher->matches('   ', 'слово'));
    }

    public function test_dedupe_key_format(): void
    {
        $this->assertSame('p:5:k:2', $this->matcher->dedupeKey('post', 2, 5, null));
        $this->assertSame('c:9:k:2', $this->matcher->dedupeKey('comment', 2, 5, 9));
        // comment without comment id falls back to post key shape
        $this->assertSame('p:5:k:2', $this->matcher->dedupeKey('comment', 2, 5, null));
    }
}
