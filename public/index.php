<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Sdcc\Greenhouse\Greenhouse;
use Sdcc\Greenhouse\StateStore;

header('Content-Type: application/json');

/** @param array<string, mixed> $body */
function send(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit;
}

$store = new StateStore(getenv('STATE_FILE') ?: '/tmp/greenhouse-state.json');
$now = microtime(true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$route = "{$method} {$path}";

try {
    if ($route === 'GET /healthz') {
        send(200, ['status' => 'ok']);
    }

    if ($route === 'GET /state') {
        $state = $store->transact(
            static fn (array $s): array => Greenhouse::advance($s, $now),
            $now,
        );

        send(200, [
            'observedAt' => gmdate('c', (int) $now),
            'readings' => Greenhouse::readings($state, $now),
            'actuators' => $state['actuators'],
        ]);
    }

    if ($route === 'POST /actuators') {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw === false || $raw === '' ? '{}' : $raw, true);

        if (! is_array($body) || $body === []) {
            send(400, ['error' => 'expected a JSON object mapping actuator to value']);
        }

        foreach ($body as $name => $value) {
            if (! is_string($name) || ! isset(Greenhouse::ACTUATORS[$name])) {
                send(400, [
                    'error' => "unknown actuator '{$name}'",
                    'known' => array_keys(Greenhouse::ACTUATORS),
                ]);
            }

            if (! in_array($value, Greenhouse::ACTUATORS[$name], true)) {
                send(400, [
                    'error' => "invalid value for '{$name}'",
                    'allowed' => Greenhouse::ACTUATORS[$name],
                ]);
            }
        }

        $state = $store->transact(static function (array $s) use ($body, $now): array {
            /* integrate up to the moment of the change before applying it,
               so elapsed time is credited to the old actuator positions */
            $s = Greenhouse::advance($s, $now);
            $s['actuators'] = [...$s['actuators'], ...$body];

            return $s;
        }, $now);

        send(200, [
            'actuators' => $state['actuators'],
            'readings' => Greenhouse::readings($state, $now),
        ]);
    }

    if ($route === 'POST /reset') {
        $store->reset();
        send(200, ['status' => 'reset']);
    }

    send(404, ['error' => 'not found', 'route' => $route]);
} catch (Throwable $e) {
    /* stderr lands in kubectl logs */
    error_log((string) $e);
    send(500, ['error' => $e->getMessage()]);
}
