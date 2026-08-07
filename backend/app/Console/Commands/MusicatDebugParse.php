<?php

namespace App\Console\Commands;

use App\Services\MusicatService;
use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Diagnostic only — not meant to stay long-term. Dumps the exact text-node
 * order MusicatService::recentlyPlayed() sees around every "Apple Music /
 * Spotify • date • time" line, so you can check whether the fixed i-1 /
 * i-2 offset (artist / track name) is actually landing on the right lines
 * for every row — e.g. rows whose artwork has an extra badge/label
 * (like JISOO's "AMORTAGE" tracks) that might inject an extra text node
 * and shift everything.
 *
 * Usage: php artisan musicat:debug-parse <handle>
 * (the part after musicat.fm/ in the profile URL)
 */
class MusicatDebugParse extends Command
{
    protected $signature = 'musicat:debug-parse {handle} {--around=6 : lines of context to show before each matched row}';
    protected $description = 'Dump raw Musicat profile text-node order around each recently-played row, for debugging parse misalignment';

    public function handle(MusicatService $musicat): int
    {
        $handle = $this->argument('handle');
        $context = (int) $this->option('around');

        $reflection = new \ReflectionClass($musicat);

        $renderMethod = $reflection->getMethod('renderProfileHtml');
        $renderMethod->setAccessible(true);
        $html = $renderMethod->invoke($musicat, $handle);

        if (! $html) {
            $this->error('Could not render the profile page — check MUSICAT_CHROME_PATH / network access / that the profile is public.');
            return self::FAILURE;
        }

        $crawler = new Crawler($html);

        $textNodesMethod = $reflection->getMethod('textNodes');
        $textNodesMethod->setAccessible(true);
        $lines = $textNodesMethod->invoke($musicat, $crawler);

        $metaPattern = '/^(Apple Music|Spotify)[^\d]{1,8}(\d{2}\.\d{2}\.\d{2})\s+(\d{1,2}:\d{2}\s*[AP]M)/iu';

        $matches = 0;

        foreach ($lines as $i => $line) {
            if (! preg_match($metaPattern, $line)) {
                continue;
            }

            $matches++;
            $this->line('---- row #' . $matches . ' ----');

            $start = max(0, $i - $context);
            for ($j = $start; $j <= $i; $j++) {
                $marker = match (true) {
                    $j === $i => ' <== metadata line (matched here)',
                    $j === $i - 1 => ' <== parsed as ARTIST NAME',
                    $j === $i - 2 => ' <== parsed as TRACK NAME',
                    default => '',
                };
                $this->line("  [{$j}] " . json_encode($lines[$j]) . $marker);
            }
        }

        if ($matches === 0) {
            $this->warn('No "Apple Music • date • time" rows matched at all — either the page didn\'t render, or the meta-line pattern needs updating.');
        } else {
            $this->info("Matched {$matches} row(s) total.");
        }

        return self::SUCCESS;
    }
}