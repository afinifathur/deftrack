<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class WeeklyBackupCommand extends Command
{
    protected $signature = 'deftrack:weekly-backup {--retention=12}';
    protected $description = 'Dump MySQL weekly to backup/ with rolling retention.';

    public function handle()
    {
        $backupDir = config('deftrack.db_backup_path');
        if (!File::exists($backupDir)) { File::makeDirectory($backupDir, 0755, true); }

        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', 3306);
        $user = env('DB_USERNAME', 'root');
        $pass = env('DB_PASSWORD', '');
        $name = env('DB_DATABASE', 'deftrack_db');
        $file = $backupDir.'/deftrack_'.now()->format('Ymd_His').'.sql';

        $cmdline = 'mysqldump --host='.$host.' --port='.$port.' --user='.$user.' --password='.$pass.' '.$name.' > "'.$file.'"';
        $process = Process::fromShellCommandline($cmdline, base_path(), null, null, 120);
        $process->run();
        if ($process->isSuccessful()) { $this->info('Backup created: '.$file); }
        else { $this->error('Backup failed: '.$process->getErrorOutput()); }

        $retention = (int)$this->option('retention');
        $files = collect(File::files($backupDir))->sortByDesc(function($f){return $f->getMTime();})->values();
        $excess = $files->slice($retention);
        foreach ($excess as $f) { File::delete($f->getRealPath()); }
        return Command::SUCCESS;
    }
}