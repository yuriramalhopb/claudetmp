<?php
declare(strict_types=1);

namespace Tests;

/**
 * Base mínima de testes (sem dependências). Métodos públicos test*() são executados.
 */
abstract class TestCase
{
    public int $assertions = 0;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    protected function assertTrue(mixed $cond, string $msg = ''): void
    {
        $this->assertions++;
        if ($cond !== true) {
            throw new AssertionFailed($msg ?: 'Esperado true, obtido ' . var_export($cond, true));
        }
    }

    protected function assertFalse(mixed $cond, string $msg = ''): void
    {
        $this->assertTrue($cond === false, $msg ?: 'Esperado false, obtido ' . var_export($cond, true));
    }

    protected function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new AssertionFailed(($msg ? $msg . ' — ' : '') . 'esperado ' . var_export($expected, true) . ', obtido ' . var_export($actual, true));
        }
    }

    protected function assertContains(string $needle, string $haystack, string $msg = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailed(($msg ? $msg . ' — ' : '') . 'texto não contém: ' . mb_substr($needle, 0, 120));
        }
    }

    protected function assertNotContains(string $needle, string $haystack, string $msg = ''): void
    {
        $this->assertions++;
        if (str_contains($haystack, $needle)) {
            throw new AssertionFailed(($msg ? $msg . ' — ' : '') . 'texto NÃO deveria conter: ' . mb_substr($needle, 0, 120));
        }
    }

    protected function assertThrows(callable $fn, string $msg = ''): \Throwable
    {
        $this->assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            return $e;
        }
        throw new AssertionFailed($msg ?: 'Esperava uma exceção.');
    }

    protected function assertEqualsFloat(float $expected, float $actual, float $delta, string $msg = ''): void
    {
        $this->assertTrue(abs($expected - $actual) <= $delta, ($msg ? $msg . ' — ' : '') . "esperado {$expected} ± {$delta}, obtido {$actual}");
    }
}

final class AssertionFailed extends \RuntimeException
{
}
