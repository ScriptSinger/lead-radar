<?php

namespace Tests\Unit;

use App\Support\PostWindow;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PostWindowTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_mode_aliases(): void
    {
        $this->assertSame(PostWindow::MODE_TODAY, PostWindow::mode('today'));
        $this->assertSame(PostWindow::MODE_TODAY, PostWindow::mode('DAY'));
        $this->assertSame(PostWindow::MODE_ALL, PostWindow::mode('all'));
        $this->assertSame(PostWindow::MODE_ALL, PostWindow::mode('none'));
        $this->assertSame(PostWindow::MODE_SINCE_LAST_SCAN, PostWindow::mode('since_last_scan'));
        $this->assertSame(PostWindow::MODE_SINCE_LAST_SCAN, PostWindow::mode('weird'));
    }

    public function test_includes_respects_cutoff(): void
    {
        $cutoff = Carbon::parse('2026-07-23 10:00:00');

        $this->assertTrue(PostWindow::includes(Carbon::parse('2026-07-23 10:00:00'), $cutoff));
        $this->assertTrue(PostWindow::includes(Carbon::parse('2026-07-23 12:00:00'), $cutoff));
        $this->assertFalse(PostWindow::includes(Carbon::parse('2026-07-23 09:59:59'), $cutoff));
        $this->assertTrue(PostWindow::includes(null, $cutoff)); // unknown date kept
        $this->assertTrue(PostWindow::includes(Carbon::parse('2020-01-01'), null)); // no filter
    }
}
