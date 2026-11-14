<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\String_;

/**
 * Works out the value of an expression without running anything: literals,
 * arrays and concatenations, translation calls such as __('Events'), and
 * the functions that return theme paths, which become placeholders such as
 * "{theme}". Anything else is unknown.
 */
final class Evaluator
{
    private const array TRANSLATIONS = ['__', '_e', '_x', '_ex', '_n', 'esc_html__', 'esc_attr__', 'esc_html_e', 'esc_attr_e', 'esc_html_x', 'esc_attr_x', 'translate'];

    private const array THEME_PATHS = [
        'get_template_directory_uri' => '{theme}',
        'get_parent_theme_file_uri' => '{theme}',
        'get_stylesheet_directory_uri' => '{stylesheet}',
        'get_theme_file_uri' => '{stylesheet}',
        'get_stylesheet_uri' => '{stylesheet}/style.css',
        'get_template_directory' => '{theme-dir}',
        'get_parent_theme_file_path' => '{theme-dir}',
        'get_stylesheet_directory' => '{stylesheet-dir}',
        'get_theme_file_path' => '{stylesheet-dir}',
    ];

    /**
     * @param  string  $directory  the directory of the file being read, relative to the theme
     */
    public function __construct(private readonly string $directory = '') {}

    public function value(?Expr $expr): mixed
    {
        return match (true) {
            $expr === null => Unknown::value(),
            $expr instanceof String_ => $expr->value,
            $expr instanceof Int_, $expr instanceof Float_ => $expr->value,
            $expr instanceof UnaryMinus => $this->negate($this->value($expr->expr)),
            $expr instanceof ConstFetch => $this->constant($expr),
            $expr instanceof Array_ => $this->array($expr),
            $expr instanceof Concat => $this->concat($this->value($expr->left), $this->value($expr->right)),
            $expr instanceof Dir => '{theme-dir}'.($this->directory === '' ? '' : '/'.$this->directory),
            $expr instanceof FuncCall => $this->call($expr),
            default => Unknown::value(),
        };
    }

    public function string(?Expr $expr): ?string
    {
        $value = $this->value($expr);

        return is_string($value) || is_int($value) ? (string) $value : null;
    }

    private function constant(ConstFetch $expr): mixed
    {
        return match (strtolower($expr->name->toString())) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => Unknown::value(),
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    private function array(Array_ $expr): array
    {
        $array = [];

        foreach ($expr->items as $item) {
            if ($item->unpack) {
                continue;
            }

            $value = $this->value($item->value);

            if ($item->key === null) {
                $array[] = $value;

                continue;
            }

            $key = $this->value($item->key);

            if (is_string($key) || is_int($key)) {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    private function concat(mixed $left, mixed $right): mixed
    {
        if ((is_string($left) || is_int($left)) && (is_string($right) || is_int($right))) {
            return $left.$right;
        }

        return Unknown::value();
    }

    private function negate(mixed $value): mixed
    {
        return is_int($value) || is_float($value) ? -$value : Unknown::value();
    }

    private function call(FuncCall $expr): mixed
    {
        if (! $expr->name instanceof Name) {
            return Unknown::value();
        }

        $name = strtolower($expr->name->toString());
        $arguments = $expr->getArgs();

        if (in_array($name, self::TRANSLATIONS, true)) {
            return $this->value($arguments[0]->value ?? null);
        }

        if (isset(self::THEME_PATHS[$name])) {
            $base = self::THEME_PATHS[$name];
            $file = isset($arguments[0]) ? $this->string($arguments[0]->value) : '';

            return $file === null ? Unknown::value() : $base.($file === '' ? '' : '/'.ltrim($file, '/'));
        }

        return Unknown::value();
    }
}
