<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Console;

use Illuminate\Console\Command;
use ItsJustVita\WpMigrator\Models\Redirect;
use Symfony\Component\Console\Output\OutputInterface;

class ExportRedirectsCommand extends Command
{
    private const array FORMATS = ['csv', 'nginx', 'apache'];

    protected $signature = 'wp:redirects:export
        {--format=csv : csv, nginx or apache}
        {--output= : Write to this file instead of the console}';

    protected $description = 'Export the redirects table for the web server or a spreadsheet';

    public function handle(): int
    {
        $format = $this->option('format');
        $format = is_string($format) ? $format : '';

        if (! in_array($format, self::FORMATS, true)) {
            $this->components->error(sprintf('Unknown format "%s". Use one of: %s.', $format, implode(', ', self::FORMATS)));

            return self::FAILURE;
        }

        $redirects = array_values(Redirect::query()->orderBy('id')->get()->all());

        $content = match ($format) {
            'csv' => $this->csv($redirects),
            'nginx' => $this->nginx($redirects),
            default => $this->apache($redirects),
        };

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            file_put_contents($output, $content);
            $this->components->info(sprintf('%d redirects written to %s', count($redirects), $output));

            return self::SUCCESS;
        }

        $this->output->write($content, false, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    /**
     * @param  list<Redirect>  $redirects
     */
    private function csv(array $redirects): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, ['from_path', 'to_path', 'status_code', 'hits'], eol: "\n");

        foreach ($redirects as $redirect) {
            fputcsv($stream, [$redirect->from_path, $redirect->to_path, $redirect->status_code, $redirect->hits], eol: "\n");
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /**
     * One map per status code, keyed by the request URI as nginx sees it:
     * percent-encoded, with and without a trailing slash.
     *
     * @param  list<Redirect>  $redirects
     */
    private function nginx(array $redirects): string
    {
        $byStatus = [];

        foreach ($redirects as $redirect) {
            $byStatus[$redirect->status_code][] = $redirect;
        }

        ksort($byStatus);

        $lines = [
            '# Redirects exported by wp:redirects:export. Put the map blocks in the',
            '# http context and the if lines at the end of them in the server block.',
            '',
        ];

        foreach ($byStatus as $status => $group) {
            $lines[] = 'map $request_uri $wp_migrator_redirect_'.$status.' {';
            $lines[] = '    default "";';

            foreach ($group as $redirect) {
                $from = $this->encodeUri($redirect->from_path);
                $to = $this->nginxValue($redirect->to_path);

                $lines[] = '    "'.$from.'" "'.$to.'";';

                if (! str_contains($from, '?') && $from !== '/') {
                    $lines[] = '    "'.$from.'/" "'.$to.'";';
                }
            }

            $lines[] = '}';
            $lines[] = '';
        }

        foreach (array_keys($byStatus) as $status) {
            $lines[] = 'if ($wp_migrator_redirect_'.$status.') { return '.$status.' $wp_migrator_redirect_'.$status.'; }';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * mod_rewrite rules. RewriteRule matches the decoded path without its
     * leading slash in .htaccess and with it in the server configuration.
     *
     * @param  list<Redirect>  $redirects
     */
    private function apache(array $redirects): string
    {
        $lines = [
            '# Redirects exported by wp:redirects:export, for the server configuration or .htaccess.',
            'RewriteEngine On',
        ];

        foreach ($redirects as $redirect) {
            $flags = 'R='.$redirect->status_code.',L,NE';
            $to = $this->quote($redirect->to_path);
            $mark = strpos($redirect->from_path, '?');

            if ($mark !== false) {
                $path = trim(substr($redirect->from_path, 0, $mark), '/');
                $lines[] = 'RewriteCond %{QUERY_STRING} "^'.$this->quote($this->escapeRegex(substr($redirect->from_path, $mark + 1))).'$"';
                $lines[] = 'RewriteRule "^/?'.$this->quote($this->escapeRegex($path)).'$" "'.$to.'?" ['.$flags.']';

                continue;
            }

            $path = trim($redirect->from_path, '/');
            $lines[] = 'RewriteRule "^/?'.$this->quote($this->escapeRegex($path)).'/?$" "'.$to.'" ['.$flags.',NC]';
        }

        return implode("\n", $lines)."\n";
    }

    private function encodeUri(string $path): string
    {
        $mark = strpos($path, '?');
        $query = $mark === false ? '' : substr($path, $mark);
        $path = $mark === false ? $path : substr($path, 0, $mark);

        $encoded = implode('/', array_map(rawurlencode(...), explode('/', $path)));

        return $encoded.str_replace(['"', ' ', '$', '\\'], ['%22', '%20', '%24', '%5C'], $query);
    }

    /**
     * Characters with a meaning in an nginx string become percent escapes.
     */
    private function nginxValue(string $url): string
    {
        return str_replace(['"', ' ', '$', '\\'], ['%22', '%20', '%24', '%5C'], $url);
    }

    private function escapeRegex(string $text): string
    {
        return (string) preg_replace('/[.\\\\+*?\[\]^$(){}|]/', '\\\\$0', $text);
    }

    private function quote(string $text): string
    {
        return str_replace('"', '\\"', $text);
    }
}
