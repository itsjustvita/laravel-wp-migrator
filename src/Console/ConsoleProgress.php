<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Console;

use Illuminate\Console\OutputStyle;
use ItsJustVita\WpMigrator\Import\ImportProgress;
use Symfony\Component\Console\Helper\ProgressBar;

final class ConsoleProgress implements ImportProgress
{
    private ?ProgressBar $bar = null;

    public function __construct(private readonly OutputStyle $output) {}

    public function start(string $importer, int $total): void
    {
        $this->output->writeln(sprintf(' <options=bold>%s</> <fg=gray>(%d)</>', ucfirst($importer), $total));
        $this->bar = $this->output->createProgressBar($total);
        $this->bar->start();
    }

    public function advance(): void
    {
        $this->bar?->advance();
    }

    public function finish(): void
    {
        $this->bar?->finish();
        $this->bar = null;
        $this->output->newLine(2);
    }
}
