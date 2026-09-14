# sdcc-greenhouse

Simulated greenhouse edge device for the Software Development for Cloud Computing project. A
framework-free PHP HTTP API standing in for the physical device the reconciler drives. Five
actuators (`pump`, `fan`, `vent`, `lamp`, `door`), a lazy physics model for temperature,
moisture, and light, and no state persisted across a restart, on purpose.

## Depends on

- PHP 8.4+, Composer
- FrankenPHP (runtime, via the included `Dockerfile`)
- A Kubernetes cluster to deploy to (manifests in `k8s/`)

## Run it

Locally:

```bash
composer install
php -S 127.0.0.1:8080 -t public
curl 127.0.0.1:8080/state
curl -XPOST 127.0.0.1:8080/actuators -d '{"pump":"on"}'
```

Tests:

```bash
composer test
```

Deployed: `scripts/bootstrap.sh` runs once, by hand, to stand up the namespace, Deployment, and
CI runner. After that, every push to `master` builds an image and rolls it out through
`.github/workflows/ci.yml` — don't `kubectl apply -k k8s/` again, CI owns the live image from
then on.
