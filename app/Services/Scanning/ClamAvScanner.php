<?php

namespace App\Services\Scanning;

use App\Contracts\VirusScanner;
use App\Support\ScanResult;
use Closure;
use RuntimeException;

/**
 * Streams file contents to a clamd daemon over TCP using its INSTREAM
 * command: "zINSTREAM\0", then chunks each prefixed with a 4-byte big-endian
 * length, then a zero-length chunk. clamd answers "stream: OK" or
 * "stream: <signature> FOUND".
 */
class ClamAvScanner implements VirusScanner
{
    private const CHUNK_BYTES = 8192;

    /**
     * @param  (Closure(): resource)|null  $connect  opens the clamd socket (overridable in tests)
     */
    public function __construct(
        private string $host,
        private int $port,
        private int $timeoutSeconds = 30,
        private ?Closure $connect = null,
    ) {}

    public function scan(string $contents): ScanResult
    {
        $socket = $this->connect ? ($this->connect)() : $this->open();

        try {
            $this->write($socket, "zINSTREAM\0");

            foreach (str_split($contents, self::CHUNK_BYTES) as $chunk) {
                $this->write($socket, pack('N', strlen($chunk)).$chunk);
            }
            $this->write($socket, pack('N', 0));

            $reply = '';
            while (! feof($socket) && ! str_contains($reply, "\0")) {
                $read = fread($socket, 1024);
                if ($read === false || ($read === '' && stream_get_meta_data($socket)['timed_out'])) {
                    throw new RuntimeException('Timed out waiting for clamd');
                }
                $reply .= $read;
            }
        } finally {
            fclose($socket);
        }

        $reply = trim($reply, "\0\r\n ");

        if ($reply === 'stream: OK') {
            return ScanResult::clean();
        }

        if (preg_match('/^stream: (.+) FOUND$/', $reply, $m)) {
            return ScanResult::infected($m[1]);
        }

        throw new RuntimeException("Unexpected clamd reply: {$reply}");
    }

    /**
     * @return resource
     */
    private function open()
    {
        $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $error, $this->timeoutSeconds);
        if ($socket === false) {
            throw new RuntimeException("Cannot reach clamd at {$this->host}:{$this->port}: {$error}");
        }

        stream_set_timeout($socket, $this->timeoutSeconds);

        return $socket;
    }

    /**
     * @param  resource  $socket
     */
    private function write($socket, string $bytes): void
    {
        for ($written = 0; $written < strlen($bytes); $written += $n) {
            $n = fwrite($socket, substr($bytes, $written));
            if ($n === false || $n === 0) {
                throw new RuntimeException('Lost connection to clamd while sending the file');
            }
        }
    }
}
