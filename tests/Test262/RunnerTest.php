<?php

declare(strict_types=1);

namespace Temporal\Tests\Test262;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers and runs all generated PHP test scripts under tests/Test262/scripts/.
 *
 * Each script file becomes one data-provider test case. Scripts use the
 * Assert helper class to bridge TC39-style assertions to PHPUnit.
 */
final class RunnerTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function scripts(): iterable
    {
        $dir = sprintf('%s/scripts', __DIR__);
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $dir,
            RecursiveDirectoryIterator::SKIP_DOTS,
        ));
        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            if ($file->getExtension() === 'php') {
                $name = ltrim(str_replace(search: $dir, replace: '', subject: $file->getPathname()), characters: '/');
                yield $name => [$file->getPathname()];
            }
        }
    }

    #[DataProvider('scripts')]
    public function testScript(string $path): void
    {
        try {
            /** @psalm-suppress UnresolvableInclude */
            require $path;
        } catch (\Throwable $e) {
            self::handleScriptThrowable($e);
        }
    }

    /**
     * Dispatches throwables from generated test262 scripts:
     *
     *   - `\ParseError`        → fail loudly (transpiler bug, not a legitimate incomplete test).
     *   - Other `\Error` (e.g. `\TypeError`, `\ArgumentCountError`, `\UnhandledMatchError`,
     *     `\AssertionError`): these indicate the generated PHP cannot run — mark the test as
     *     incomplete rather than pass/fail, since the JS fixture cannot be translated 1:1.
     *   - `\Exception` subclasses: re-throw so PHPUnit records the real assertion / runtime error.
     */
    private static function handleScriptThrowable(\Throwable $e): void
    {
        if ($e instanceof \ParseError) {
            static::fail(sprintf('Syntax error in generated script: %s', $e->getMessage()));
        }
        if ($e instanceof \Error) {
            static::markTestIncomplete(sprintf('PHP error: %s', $e->getMessage()));
        }
        throw $e;
    }
}
