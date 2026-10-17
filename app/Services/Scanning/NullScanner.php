<?php

namespace App\Services\Scanning;

use App\Contracts\VirusScanner;
use App\Support\ScanResult;

/**
 * Development only: performs no scan. Files are stored as "unscanned".
 */
class NullScanner implements VirusScanner
{
    public function scan(string $contents): ScanResult
    {
        return ScanResult::skipped();
    }
}
