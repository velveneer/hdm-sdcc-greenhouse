# sdcc-greenhouse

Simulated greenhouse edge device for the Software Development for Cloud Computing lecture
project, plus (later) the GitOps reconciler that drives it.

## The device

A framework-free PHP service on FrankenPHP. Five actuators, three sensors, and a lazy physics
model — there is no background ticker, state is integrated forward from the last write whenever
someone reads it.

| Method | Path         | Purpose                                   |
| ------ | ------------ | ------------------------------------------ |
| GET    | `/healthz`   | Liveness. Never touches state.              |
| GET    | `/state`     | Current readings and actuator positions.    |
| POST   | `/actuators` | `{"pump": "on", "door": "open"}`            |
| POST   | `/reset`     | Wipe state. Useful mid-demo.                |

Actuators: `pump`, `fan`, `vent` and `lamp` take `on`/`off`; `door` takes `open`/`closed`. `vent`
is the roof exhaust (a weaker cooling path than the indoor `fan`); `door` is the physical access
point, and open also means more heat and light exchange with outside.

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

## Deployment

`scripts/bootstrap.sh` runs once, by hand. After that, every push to `master` builds an image
and rolls it out via the self-hosted runner in `sdcc-greenhouse`.

The image reference in `k8s/deployment.yaml` is bootstrap-only. CI updates the live Deployment
imperatively with `kubectl set image`, so that file goes stale the moment CI runs. **Do not
`apply -k k8s/` after bootstrap.**

## Consumed by

`sdcc-docs` reaches the device in-cluster at
`http://greenhouse.sdcc-greenhouse.svc.cluster.local:8080`, already wired as `GREENHOUSE_URL` in
that Deployment.
