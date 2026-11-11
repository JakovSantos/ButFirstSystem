<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\App;
use App\Core\Logger;

/**
 * External API integration: Nager.Date public holidays.
 *
 *   GET https://date.nager.at/api/v3/PublicHolidays/{year}/{countryCode}
 *   Response: JSON array of {date, localName, name, countryCode, types[] ...}
 *
 * - No authentication / API key is required by the provider.
 * - Responses are cached on disk for a day (config holidays_api.cache_ttl) to
 *   stay well within fair-use rate limits and to keep pages fast.
 * - Network/HTTP/JSON failures never break a page: callers get an empty list plus
 *   an error message, and a stale cache file is used when one exists.
 */
final class HolidayApi
{
    private string $baseUrl;
    private string $country;
    private int $timeout;
    private string $cacheDir;
    private int $cacheTtl;

    public ?string $lastError = null;
    public string $source = 'none';

    public function __construct(?array $config = null)
    {
        $config ??= (array) App::config('holidays_api', []);
        $this->baseUrl  = rtrim((string) ($config['base_url'] ?? 'https://date.nager.at/api/v3'), '/');
        $this->country  = strtoupper((string) ($config['country'] ?? 'PH'));
        $this->timeout  = (int) ($config['timeout'] ?? 8);
        $this->cacheDir = (string) ($config['cache_dir'] ?? sys_get_temp_dir());
        $this->cacheTtl = (int) ($config['cache_ttl'] ?? 86400);
    }

    /**
     * Holidays for one year, normalized to
     * [ ['date' => 'Y-m-d', 'name' => ..., 'localName' => ..., 'type' => 'Regular'|'Special'] ].
     *
     * @return list<array{date:string,name:string,localName:string,type:string}>
     */
    public function forYear(int $year): array
    {
        if ($year < 1975 || $year > 2100) {
            $this->lastError = 'Year out of range.';
            return [];
        }

        $cacheFile = sprintf('%s/holidays-%s-%d.json', $this->cacheDir, $this->country, $year);

        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheTtl) {
            $this->source = 'cache';
            return $this->normalize(json_decode((string) file_get_contents($cacheFile), true) ?: []);
        }

        $url = sprintf('%s/PublicHolidays/%d/%s', $this->baseUrl, $year, $this->country);
        [$status, $body, $error] = $this->get($url);

        if ($error !== null || $status !== 200) {
            $this->lastError = $error ?? "Holiday API responded with HTTP {$status}.";
            Logger::error('HolidayApi: ' . $this->lastError . " ({$url})");

            if (is_file($cacheFile)) { // fall back to stale cache
                $this->source = 'stale-cache';
                return $this->normalize(json_decode((string) file_get_contents($cacheFile), true) ?: []);
            }
            return [];
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            $this->lastError = 'Holiday API returned invalid JSON.';
            Logger::error('HolidayApi: invalid JSON');
            return [];
        }

        @file_put_contents($cacheFile, $body, LOCK_EX);
        $this->source = 'api';

        return $this->normalize($data);
    }

    /**
     * Holidays between two dates (inclusive), across year boundaries.
     *
     * @return list<array{date:string,name:string,localName:string,type:string}>
     */
    public function between(string $start, string $end): array
    {
        $s = strtotime($start);
        $e = strtotime($end);
        if ($s === false || $e === false) {
            $this->lastError = 'Invalid date range.';
            return [];
        }
        if ($s > $e) {
            [$s, $e] = [$e, $s];
        }
        // A pay period never spans more than a year or two; a huge range would mean
        // dozens of calls to the external service for a single request.
        if ((int) date('Y', $e) - (int) date('Y', $s) > 1) {
            $this->lastError = 'The date range can cover at most two calendar years.';
            return [];
        }

        $result = [];
        for ($y = (int) date('Y', $s); $y <= (int) date('Y', $e); $y++) {
            foreach ($this->forYear($y) as $h) {
                $t = strtotime($h['date']);
                if ($t >= $s && $t <= $e) {
                    $result[] = $h;
                }
            }
        }

        return $result;
    }

    /**
     * @return array{0:int,1:string,2:?string} status, body, error
     */
    private function get(string $url): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_USERAGENT      => 'ButFirstSystem-PHP/1.0',
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = $body === false ? curl_error($ch) : null;
            curl_close($ch);

            return [$status, (string) $body, $error ?: null];
        }

        $ctx = stream_context_create(['http' => [
            'timeout' => $this->timeout,
            'header'  => "Accept: application/json\r\nUser-Agent: ButFirstSystem-PHP/1.0\r\n",
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }

        return [$status, (string) $body, $body === false ? 'Could not reach the holiday API.' : null];
    }

    /**
     * Map the provider's "types" to the payroll Holiday Type combobox values.
     * Public holidays are "Regular"; everything else (Optional, Observance, ...) is "Special".
     *
     * @param array<int, mixed> $rows
     * @return list<array{date:string,name:string,localName:string,type:string}>
     */
    private function normalize(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r) || ! isset($r['date'], $r['name'])) {
                continue;
            }
            $types = (array) ($r['types'] ?? []);
            $out[] = [
                'date'      => (string) $r['date'],
                'name'      => (string) $r['name'],
                'localName' => (string) ($r['localName'] ?? $r['name']),
                'type'      => in_array('Public', $types, true) ? 'Regular' : 'Special',
            ];
        }

        return $out;
    }
}
