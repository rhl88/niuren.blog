<?php

namespace App\Apps\NiurenBlog;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Install
{
    public function install(): void
    {
        $this->runMigrations();
    }

    public function uninstall(): void
    {
        $this->rollbackMigrations();
        Cache::flush();
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->runMigrations();
    }

    protected function runMigrations(): void
    {
        $migrationsPath = __DIR__ . '/Migrations';
        $migrationFiles = glob($migrationsPath . '/*.php');

        foreach ($migrationFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            if (DB::table('migrations')->where('migration', $migrationName)->exists()) {
                continue;
            }

            $migration = require $file;
            if (method_exists($migration, 'up')) {
                try {
                    $migration->up();

                    DB::table('migrations')->insert([
                        'migration' => $migrationName,
                        'batch' => DB::table('migrations')->max('batch') + 1,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('应用迁移执行失败', [
                        'app' => 'niuren.blog',
                        'file' => basename($file),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    protected function rollbackMigrations(): void
    {
        $migrationsPath = __DIR__ . '/Migrations';
        $migrationFiles = glob($migrationsPath . '/*.php');

        foreach (array_reverse($migrationFiles) as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            if (!DB::table('migrations')->where('migration', $migrationName)->exists()) {
                continue;
            }

            $migration = require $file;
            if (method_exists($migration, 'down')) {
                try {
                    $migration->down();

                    DB::table('migrations')->where('migration', $migrationName)->delete();
                } catch (\Throwable $e) {
                    Log::error('应用迁移回滚失败', [
                        'app' => 'niuren.blog',
                        'file' => basename($file),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
