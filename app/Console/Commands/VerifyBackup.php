<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Proves the latest backup can be restored: decrypts it, loads the dump
 * into a throwaway database, compares row counts with the live database,
 * then drops the throwaway database.
 */
class VerifyBackup extends Command
{
    protected $signature = 'navesta:verify-backup
        {--database=navesta_restore_check : Scratch database to restore into (must end in _restore_check)}
        {--keep : Keep the scratch database for inspection}';

    protected $description = 'Restore the latest database backup into a scratch database and compare it with the live data';

    private const TABLES = ['users', 'credit_cards', 'loans', 'lenders', 'bt_applications', 'credit_scores', 'debt_snapshots', 'posts', 'statements'];

    public function handle(): int
    {
        $target = (string) $this->option('database');
        if (! str_ends_with($target, '_restore_check') || ! preg_match('/^[a-z0-9_]+$/', $target)) {
            $this->error('The scratch database name must be lowercase and end in _restore_check.');

            return self::FAILURE;
        }

        $workDir = storage_path('app/backup-verify/'.now()->format('YmdHis'));
        File::ensureDirectoryExists($workDir);

        try {
            $sql = $this->extractLatestDump($workDir);
            $this->restore($sql, $target);
            $mismatches = $this->compare($target);
        } finally {
            File::deleteDirectory($workDir);
            if (! $this->option('keep')) {
                DB::statement("DROP DATABASE IF EXISTS {$target}");
            }
        }

        if ($mismatches > 0) {
            $this->error("Restore check FAILED: {$mismatches} table(s) differ.");

            return self::FAILURE;
        }

        $this->info('Restore check passed: the latest backup restores cleanly.');

        return self::SUCCESS;
    }

    private function extractLatestDump(string $workDir): string
    {
        $disk = Storage::disk(explode(',', (string) config('backup.backup.destination.disks')[0])[0]);
        $latest = collect($disk->allFiles(config('backup.backup.name')))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->sort()
            ->last() ?? throw new RuntimeException('No backups found');

        $this->line("Latest backup: {$latest}");

        $zipPath = "{$workDir}/backup.zip";
        File::put($zipPath, $disk->get($latest));

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Cannot open the backup archive');
        }
        if ($password = config('backup.backup.password')) {
            $zip->setPassword($password);
        }
        if (! $zip->extractTo($workDir)) {
            throw new RuntimeException('Cannot extract the backup (wrong BACKUP_ARCHIVE_PASSWORD?)');
        }
        $zip->close();

        $dump = collect(File::allFiles($workDir))->first(fn ($f) => str_ends_with($f->getFilename(), '.sql.gz') || str_ends_with($f->getFilename(), '.sql'))
            ?? throw new RuntimeException('No database dump inside the backup');

        if (str_ends_with($dump->getFilename(), '.sql')) {
            return $dump->getPathname();
        }

        $sqlPath = "{$workDir}/restore.sql";
        $in = gzopen($dump->getPathname(), 'rb');
        $out = fopen($sqlPath, 'wb');
        while (! gzeof($in)) {
            fwrite($out, gzread($in, 1 << 20));
        }
        gzclose($in);
        fclose($out);

        return $sqlPath;
    }

    private function restore(string $sqlPath, string $target): void
    {
        $db = config('database.connections.'.config('database.default'));

        DB::statement("DROP DATABASE IF EXISTS {$target}");
        DB::statement("CREATE DATABASE {$target}");

        $binDir = rtrim((string) ($db['dump']['dump_binary_path'] ?? ''), '/\\');
        $psql = $binDir !== '' ? "{$binDir}/psql" : 'psql';

        $result = Process::env(['PGPASSWORD' => $db['password']])
            ->timeout(1800)
            ->run([$psql, '-h', $db['host'], '-p', (string) $db['port'], '-U', $db['username'], '-d', $target, '-v', 'ON_ERROR_STOP=1', '-q', '-f', $sqlPath]);

        if ($result->failed()) {
            throw new RuntimeException('psql restore failed: '.$result->errorOutput());
        }
    }

    private function compare(string $target): int
    {
        config(['database.connections.restore_check' => array_merge(
            config('database.connections.'.config('database.default')),
            ['database' => $target],
        )]);
        $restored = DB::connection('restore_check');

        $rows = [];
        $mismatches = 0;
        foreach (self::TABLES as $table) {
            $live = DB::table($table)->count();
            $copy = $restored->table($table)->count();
            $rows[] = [$table, $live, $copy, $live === $copy ? 'ok' : 'DIFFERENT'];
            $mismatches += $live === $copy ? 0 : 1;
        }

        DB::purge('restore_check');
        $this->table(['Table', 'Live rows', 'Restored rows', 'Result'], $rows);

        return $mismatches;
    }
}
