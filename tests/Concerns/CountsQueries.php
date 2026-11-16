<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait CountsQueries
{
    protected function queryCount(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    /**
     * Membuktikan tidak ada N+1: halaman dengan lebih banyak baris tidak boleh memakai lebih banyak query.
     *
     * @param  callable(): void  $grow  menambah data
     */
    protected function assertConstantQueries(string $url, ?User $actor, callable $grow, int $ceiling = 30): void
    {
        $measure = fn (): int => $this->queryCount(function () use ($url, $actor) {
            ($actor ? $this->actingAs($actor)->get($url) : $this->get($url))->assertOk();
        });

        $before = $measure();
        $grow();
        $after = $measure();

        $this->assertLessThanOrEqual(
            $before + 1,
            $after,
            "Jumlah query bertambah dari {$before} menjadi {$after} di {$url}: kemungkinan N+1 (periksa eager loading)."
        );
        $this->assertLessThanOrEqual($ceiling, $after, "Halaman {$url} memakai {$after} query (batas {$ceiling}).");
    }
}
