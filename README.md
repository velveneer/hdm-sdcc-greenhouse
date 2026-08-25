# sdcc-greenhouse

Simulated greenhouse edge device for the Software Development for Cloud Computing lecture
project, plus (later) the GitOps reconciler that drives it.

## The device

A framework-free PHP service on FrankenPHP. Three sensors, four actuators, and a lazy physics
model — there is no background ticker, state is integrated forward from the last write whenever
someone reads it.

| Method | Path         | Purpose                                   |
| ------ | ------------ | ------------------------------------------ |
| GET    | `/healthz`   | Liveness. Never touches state.              |
| GET    | `/state`     | Current readings and actuator positions.    |
| POST   | `/actuators` | `{"pump": "on", "window": "open"}`          |
| POST   | `/reset`     | Wipe state. Useful mid-demo.                |

Actuators: `pump`, `fan` and `lamp` take `on`/`off`; `window` takes `open`/`closed`.

State resets when the pod restarts. That's deliberate — a device that forgets its actuator
positions is the cleanest way to show a reconciler restoring desired state without anyone
intervening.

## Local development

```bash
composer install
php -S 127.0.0.1:8080 -t public
curl 127.0.0.1:8080/state
curl -XPOST 127.0.0.1:8080/actuators -d '{"pump":"on"}'
```
