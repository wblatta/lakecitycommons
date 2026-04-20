<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConfigCheck extends Command
{
    protected $signature = 'config:check';
    protected $description = 'Verify production configuration values are set correctly';

    public function handle(): int
    {
        $isProduction = config('app.env') === 'production';
        $issues = [];

        if (config('app.debug')) {
            $issues[] = ['APP_DEBUG', 'true', 'false', 'critical'];
        }
        if (!config('session.secure')) {
            $issues[] = ['SESSION_SECURE_COOKIE', 'false', 'true', 'critical'];
        }
        if (config('session.same_site') !== 'strict') {
            $issues[] = ['SESSION_SAME_SITE', config('session.same_site'), 'strict', 'warning'];
        }
        if (!$isProduction) {
            $this->warn('APP_ENV is not "production" — skipping critical exit.');
        }

        if (empty($issues)) {
            $this->info('All production config checks passed.');
            return self::SUCCESS;
        }

        $hasCritical = false;
        foreach ($issues as [$key, $current, $expected, $severity]) {
            if ($severity === 'critical') {
                $this->error("CRITICAL: {$key} is '{$current}', expected '{$expected}'");
                $hasCritical = true;
            } else {
                $this->warn("WARNING: {$key} is '{$current}', expected '{$expected}'");
            }
        }

        return ($hasCritical && $isProduction) ? self::FAILURE : self::SUCCESS;
    }
}
