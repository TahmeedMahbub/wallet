<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Signature('wallet:backup-database {--keep=14 : Days of backups to keep} {--path= : Folder for the backups (default storage/app/private/backups)}')]
#[Description('Write a compressed dump of the whole database (operator backup, never served over the web)')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $config = config('database.connections.'.config('database.default'));
        $folder = $this->option('path') ?: storage_path('app/private/backups');
        File::ensureDirectoryExists($folder, 0700);

        $raw = $folder.'/wallet-'.now()->utc()->format('Ymd-His').'.sql';
        $result = Process::env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])
            ->timeout(900)
            ->run(self::dumpCommand($config, $raw));

        if ($result->failed()) {
            @unlink($raw);
            $reason = trim(explode("\n", $result->errorOutput())[0]);
            $this->components->error('The database dump failed (exit code '.$result->exitCode().')'.($reason !== '' ? ': '.$reason : '').'. Check that mariadb-dump or mysqldump is installed.');

            return self::FAILURE;
        }

        $zipped = $raw.'.gz';
        $in = fopen($raw, 'rb');
        $out = gzopen($zipped, 'wb9');

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }

        fclose($in);
        gzclose($out);
        unlink($raw);
        @chmod($zipped, 0600);

        $removed = 0;
        foreach (File::glob($folder.'/wallet-*.sql.gz') as $old) {
            if (filemtime($old) < now()->subDays((int) $this->option('keep'))->getTimestamp()) {
                unlink($old);
                $removed++;
            }
        }

        $this->components->info('Backup written to '.basename($zipped).' ('.round(filesize($zipped) / 1024, 1)." KB). Removed $removed old backups.");

        return self::SUCCESS;
    }

    /**
     * The dump command. The password is never part of it (it is passed in the
     * MYSQL_PWD environment variable) so it cannot show up in process lists or logs.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    public static function dumpCommand(array $config, string $resultFile): array
    {
        $binary = env('DB_DUMP_BINARY') ?: (self::onPath('mariadb-dump') ? 'mariadb-dump' : 'mysqldump');

        $mysqldump8 = str_contains(basename($binary), 'mysqldump');

        return [
            $binary,
            ...($mysqldump8 ? ['--column-statistics=0'] : []),
            '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces', '--default-character-set=utf8mb4',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '3306'),
            '--user='.($config['username'] ?? ''),
            '--result-file='.$resultFile,
            (string) ($config['database'] ?? ''),
        ];
    }

    private static function onPath(string $binary): bool
    {
        $finder = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';

        return Process::run([$finder, $binary])->successful();
    }
}
