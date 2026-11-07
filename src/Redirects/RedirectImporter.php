<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Redirects;

use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\Importers\Importer;
use ItsJustVita\WpMigrator\Import\ImportOptions;
use ItsJustVita\WpMigrator\Import\ImportProgress;
use ItsJustVita\WpMigrator\Models\Redirect;
use Throwable;

/**
 * Writes a redirect for every old URL whose new URL differs. Runs last, so
 * attachment pages can point to the imported files. Hit counts survive.
 */
final class RedirectImporter implements Importer
{
    /** @var list<RedirectCandidate>|null */
    private ?array $candidates = null;

    public function __construct(private readonly OldUrls $urls) {}

    public function name(): string
    {
        return 'redirects';
    }

    public function shouldRun(ImportOptions $options): bool
    {
        return $options->includes('redirects');
    }

    public function count(ImportOptions $options): int
    {
        return count($this->candidates());
    }

    public function run(ImportOptions $options, ImportContext $context, ImportProgress $progress): void
    {
        $report = $context->report;
        $report->begin($this->name());
        $seen = [];

        foreach ($this->candidates() as $candidate) {
            $progress->advance();

            if ($candidate->to === null) {
                $report->skipped($this->name(), $candidate->sourceId, 'No new URL for '.$candidate->label);

                continue;
            }

            $target = RedirectPath::normalise($candidate->to);

            foreach ($candidate->from as $from) {
                $from = RedirectPath::normalise($from);

                if ($from === $target || isset($seen[$from])) {
                    continue;
                }

                $seen[$from] = true;

                try {
                    $this->write($from, $candidate->to, $context);
                } catch (Throwable $exception) {
                    $report->failed($this->name(), $candidate->sourceId, $exception);
                }
            }
        }
    }

    private function write(string $from, string $to, ImportContext $context): void
    {
        $redirect = Redirect::query()->where('from_path', $from)->first();

        if ($redirect === null) {
            Redirect::query()->create(['from_path' => $from, 'to_path' => $to]);
            $context->report->created($this->name());

            return;
        }

        if ($redirect->to_path === $to) {
            $context->report->unchanged($this->name());

            return;
        }

        $redirect->update(['to_path' => $to]);
        $context->report->updated($this->name());
    }

    /**
     * @return list<RedirectCandidate>
     */
    private function candidates(): array
    {
        return $this->candidates ??= iterator_to_array($this->urls->candidates(), false);
    }
}
