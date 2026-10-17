<?php

namespace App\Contracts;

use App\Support\ScanResult;

interface VirusScanner
{
    /**
     * Scan file contents. Throws when the scanner cannot be reached, so the
     * caller can retry instead of treating the file as clean.
     */
    public function scan(string $contents): ScanResult;
}
