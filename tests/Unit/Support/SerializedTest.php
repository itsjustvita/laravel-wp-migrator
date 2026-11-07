<?php

declare(strict_types=1);

use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\Serialized;

it('recognises serialized php values', function (string $value, bool $expected) {
    expect(Serialized::looksSerialized($value))->toBe($expected);
})->with([
    'array' => ['a:1:{s:3:"key";s:5:"value";}', true],
    'string' => ['s:3:"abc";', true],
    'int' => ['i:42;', true],
    'bool' => ['b:0;', true],
    'null' => ['N;', true],
    'object' => ['O:8:"stdClass":0:{}', true],
    'plain text' => ['hello world', false],
    'json' => ['{"a":1}', false],
    'empty' => ['', false],
    'number' => ['42', false],
    'truncated array' => ['a:2:{s:3:"key";', true],
    'text resembling an array' => ['a:1 something', false],
    'text resembling a string' => ['s:5 of 10', false],
    'text resembling an int' => ['i:5', false],
    'text resembling a double' => ['d:4 percent', false],
]);

it('decodes serialized arrays and scalars', function () {
    expect(Serialized::decode('a:2:{s:3:"key";s:5:"value";i:0;i:7;}'))->toBe(['key' => 'value', 0 => 7])
        ->and(Serialized::decode('i:42;'))->toBe(42)
        ->and(Serialized::decode('b:0;'))->toBeFalse()
        ->and(Serialized::decode('N;'))->toBeNull();
});

it('returns plain strings unchanged', function () {
    expect(Serialized::decode('hello'))->toBe('hello')
        ->and(Serialized::decode(''))->toBe('');
});

it('never instantiates classes', function () {
    $decoded = Serialized::decode('O:8:"stdClass":1:{s:1:"a";i:1;}');

    expect($decoded)->toBeInstanceOf(__PHP_Incomplete_Class::class);
});

it('throws on malformed serialized values', function () {
    expect(fn () => Serialized::decode('a:2:{s:3:"key";s:5:"value";}'))
        ->toThrow(MalformedSerializedValue::class);
});
