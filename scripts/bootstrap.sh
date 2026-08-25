#!/usr/bin/env bash
set -euo pipefail

NS=sdcc-greenhouse

kubectl apply -f k8s/namespace.yaml

kubectl create secret generic gha-runner-token -n "$NS" \
  --from-literal=token="$GH_PAT" \
  --dry-run=client -o yaml | kubectl apply -f -

kubectl apply -f k8s/runner.yaml
kubectl -n "$NS" rollout status deploy/gha-runner --timeout=180s

kubectl apply -f k8s/deployment.yaml
kubectl apply -f k8s/service.yaml
kubectl -n "$NS" rollout status deploy/greenhouse --timeout=180s

echo
echo "Bootstrap complete. Verify with:"
echo "  kubectl -n $NS get pods"
echo "  kubectl -n $NS port-forward svc/greenhouse 8081:8080"
echo "  curl localhost:8081/state"
