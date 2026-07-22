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

    public function test_matches_substring_case_insensitive(): void
    {
        $this->assertTrue($this->matcher->matches('Требуется помощник', 'требуется'));
        $this->assertTrue($this->matcher->matches('Ищу ПОДРАБОТКУ', 'подработк')); // stem in both forms
        $this->assertTrue($this->matcher->matches('Ищу подработку', 'подработку'));
        $this->assertFalse($this->matcher->matches('Привет всем', 'работа'));
    }

    public function test_dedupe_key_format(): void
    {
        $this->assertSame('p:5:k:2', $this->matcher->dedupeKey('post', 2, 5, null));
        $this->assertSame('c:9:k:2', $this->matcher->dedupeKey('comment', 2, 5, 9));
    }
}
