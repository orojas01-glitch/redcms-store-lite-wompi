#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
PHP_CLI=${PHP_CLI:-php}

if ! command -v "$PHP_CLI" >/dev/null 2>&1 && [ ! -x "$PHP_CLI" ]; then
    echo "PHP CLI not found or not executable: $PHP_CLI" >&2
    exit 1
fi

for file in \
    "$PROJECT_DIR/src/WompiNequiRequestPlanner.php" \
    "$PROJECT_DIR/src/WompiNequiResponseGate.php" \
    "$PROJECT_DIR/src/WompiNequiSealedTransportDouble.php" \
    "$PROJECT_DIR/src/WompiSandboxEventVerifier.php" \
    "$PROJECT_DIR/src/WompiMerchantContractRequestPlanner.php" \
    "$PROJECT_DIR/src/WompiMerchantContractResponseGate.php" \
    "$PROJECT_DIR/src/WompiContractConsentPresentation.php" \
    "$PROJECT_DIR/src/WompiContractConsentEvidence.php" \
    "$PROJECT_DIR/src/WompiNequiTransientWireRequestBuilder.php" \
    "$PROJECT_DIR/src/WompiNequiOfflineAdapter.php" \
    "$PROJECT_DIR/package/addon.php" \
    "$PROJECT_DIR/package/WompiNequiRequestPlanner.php" \
    "$PROJECT_DIR/package/WompiNequiResponseGate.php" \
    "$PROJECT_DIR/package/WompiNequiSealedTransportDouble.php" \
    "$PROJECT_DIR/package/WompiSandboxEventVerifier.php" \
    "$PROJECT_DIR/package/WompiMerchantContractRequestPlanner.php" \
    "$PROJECT_DIR/package/WompiMerchantContractResponseGate.php" \
    "$PROJECT_DIR/package/WompiContractConsentPresentation.php" \
    "$PROJECT_DIR/package/WompiContractConsentEvidence.php" \
    "$PROJECT_DIR/package/WompiNequiTransientWireRequestBuilder.php" \
    "$PROJECT_DIR/package/WompiNequiOfflineAdapter.php" \
    "$PROJECT_DIR/tests/c2-offline-contract-self-test.php" \
    "$PROJECT_DIR/tests/c2-package-self-test.php" \
    "$PROJECT_DIR/tests/c4b1-merchant-contract-preflight-self-test.php" \
    "$PROJECT_DIR/tests/c4b2-consent-wire-preflight-self-test.php"
do
    "$PHP_CLI" -l "$file"
done

"$PHP_CLI" "$PROJECT_DIR/tests/c2-offline-contract-self-test.php"
"$PHP_CLI" "$PROJECT_DIR/tests/c2-package-self-test.php"
"$PHP_CLI" "$PROJECT_DIR/tests/c4b1-merchant-contract-preflight-self-test.php"
"$PHP_CLI" "$PROJECT_DIR/tests/c4b2-consent-wire-preflight-self-test.php"
