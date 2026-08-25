<?php

declare(strict_types=1);

namespace Sdcc\Greenhouse;

use RuntimeException;
use Throwable;

final class StateStore
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutate
     * @return array<string, mixed> the state after mutation
     */
    public function transact(callable $mutate, float $now): array
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException("cannot create state directory {$directory}");
        }

        $handle = fopen($this->path, 'c+');

        if ($handle === false) {
            throw new RuntimeException("cannot open state file {$this->path}");
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException("cannot lock state file {$this->path}");
            }

            $raw = stream_get_contents($handle);

            $state = ($raw === '' || $raw === false)
                ? Greenhouse::initial($now)
                : $this->decode($raw, $now);

            $state = $mutate($state);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);

            return $state;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function reset(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $raw, float $now): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return Greenhouse::initial($now);
        }

        return is_array($decoded) && isset($decoded['actuators'], $decoded['updatedAt'])
            ? $decoded
            : Greenhouse::initial($now);
    }
}
