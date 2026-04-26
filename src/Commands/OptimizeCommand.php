<?php

declare(strict_types=1);

namespace Kirago\Routify\Commands;

use Illuminate\Console\Command;

final class OptimizeCommand extends Command
{
    /** @var string */
    protected $signature = 'routify:optimize';

    /** @var string */
    protected $description = "Clear and re-warm Routify's discovery cache in one call. Designed for CI/CD pipelines.";

    public function handle(): int
    {
        if ($this->call('routify:clear') !== self::SUCCESS) {
            return self::FAILURE;
        }

        return $this->call('routify:cache');
    }
}
