<?php

declare(strict_types=1);

/** Pure C4B4A authorization, claim-preparation, and state projection. */
final class RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract
{
    public static function authorize(
        array $plan,
        array $wireEvidence,
        array $scope,
        int $nowEpoch
    ): array {
        $result = self::authorizationResult('authorization_refused');
        if (!RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::valid($plan)
            || !RED_CMS_Store_Lite_Wompi_Nequi_Transient_Wire_Request_Builder::valid(
                $wireEvidence
            )
            || !self::scopeValid($scope, $nowEpoch)
            || $plan['orderId'] !== $wireEvidence['orderId']
            || $plan['amountMinor'] !== $wireEvidence['amountMinor']
        ) {
            $result['errors'] = ['authorization_refused'];
            return $result;
        }

        $result['valid'] = true;
        $result['status'] = 'authorized_no_contact_attempt';
        $result['orderId'] = $plan['orderId'];
        $result['amountMinor'] = $plan['amountMinor'];
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'secretAvailabilitySha256', 'authorizationNonceSha256',
            'issuedAtEpoch', 'expiresAtEpoch',
            'ownerAuthorityRevalidated', 'orderAuthorityRevalidated',
            'packageEnabled', 'storeEnabled', 'oneAttemptConfirmed',
            'noRetryConfirmed', 'networkDisabledConfirmed',
            'providerContactDenied', 'providerMutationDenied',
            'orderMutationDenied',
        ] as $key) {
            $result[$key] = $scope[$key];
        }
        $result['planSha256'] = $plan['planSha256'];
        $result['requestEvidenceSha256'] =
            $plan['requestEvidenceSha256'];
        $result['wireRequestSha256'] =
            $wireEvidence['wireRequestSha256'];
        $result['wireEvidenceSha256'] =
            $wireEvidence['buildEvidenceSha256'];
        $result['consentEvidenceSha256'] =
            $wireEvidence['consentEvidenceSha256'];
        $result['maximumAttempts'] = 1;
        $result['noContactAttemptAuthorized'] = true;
        $result['authorizationSha256'] = self::fingerprint(
            $result,
            'authorizationSha256'
        );
        if (!self::validAuthorization($result, $nowEpoch)) {
            return self::authorizationResult(
                'authorization_encoding_failed'
            );
        }
        return $result;
    }

    public static function validAuthorization(
        array $authorization,
        int $evaluatedAtEpoch
    ): bool {
        return array_keys($authorization)
                === array_keys(self::authorizationResult())
            && ($authorization['valid'] ?? null) === true
            && ($authorization['status'] ?? null)
                === 'authorized_no_contact_attempt'
            && self::commonIdentityValid($authorization)
            && self::scopeProjectionValid($authorization, $evaluatedAtEpoch)
            && self::sha256($authorization['planSha256'] ?? null)
            && self::sha256(
                $authorization['requestEvidenceSha256'] ?? null
            )
            && self::sha256($authorization['wireRequestSha256'] ?? null)
            && self::sha256($authorization['wireEvidenceSha256'] ?? null)
            && self::sha256(
                $authorization['consentEvidenceSha256'] ?? null
            )
            && ($authorization['maximumAttempts'] ?? null) === 1
            && ($authorization['noContactAttemptAuthorized'] ?? null)
                === true
            && self::allEffectsFalse($authorization)
            && ($authorization['durableClaimRequired'] ?? null) === true
            && ($authorization['authorizationPersisted'] ?? null) === false
            && ($authorization['claimPersisted'] ?? null) === false
            && self::sha256($authorization['authorizationSha256'] ?? null)
            && hash_equals(
                $authorization['authorizationSha256'],
                self::fingerprint($authorization, 'authorizationSha256')
            )
            && ($authorization['errors'] ?? null) === [];
    }

    public static function prepareClaim(
        array $authorization,
        array $input,
        int $nowEpoch
    ): array {
        $result = self::claimResult('claim_refused');
        if (!self::validAuthorization($authorization, $nowEpoch)
            || array_keys($input) !== [
                'authorizationSha256', 'claimNonceSha256',
                'claimedAtEpoch', 'attemptNumber',
                'priorClaimEvidenceSha256', 'oneAttemptConfirmed',
                'noRetryConfirmed', 'durableClaimRequired',
            ]
            || !is_string($input['authorizationSha256'] ?? null)
            || !hash_equals(
                $authorization['authorizationSha256'],
                $input['authorizationSha256']
            )
            || !self::sha256($input['claimNonceSha256'] ?? null)
            || hash_equals(
                $authorization['authorizationNonceSha256'],
                $input['claimNonceSha256']
            )
            || ($input['claimedAtEpoch'] ?? null) !== $nowEpoch
            || $nowEpoch < $authorization['issuedAtEpoch']
            || $nowEpoch >= $authorization['expiresAtEpoch']
            || ($input['attemptNumber'] ?? null) !== 1
            || ($input['priorClaimEvidenceSha256'] ?? null) !== []
            || ($input['oneAttemptConfirmed'] ?? null) !== true
            || ($input['noRetryConfirmed'] ?? null) !== true
            || ($input['durableClaimRequired'] ?? null) !== true
        ) {
            $result['errors'] = ['claim_refused'];
            return $result;
        }

        $result['valid'] = true;
        $result['status'] = 'claim_prepared_no_contact_attempt';
        foreach ([
            'orderId', 'amountMinor', 'clientScopeSha256', 'databaseSha256',
            'actorSubjectSha256', 'authorizationNonceSha256',
            'planSha256', 'wireRequestSha256', 'wireEvidenceSha256',
            'issuedAtEpoch', 'expiresAtEpoch', 'maximumAttempts',
        ] as $key) {
            $result[$key] = $authorization[$key];
        }
        $result['authorizationSha256'] =
            $authorization['authorizationSha256'];
        $result['claimNonceSha256'] = $input['claimNonceSha256'];
        $result['claimedAtEpoch'] = $input['claimedAtEpoch'];
        $result['attemptNumber'] = 1;
        $result['remainingAttempts'] = 0;
        $result['priorClaimCount'] = 0;
        $result['oneAttemptConfirmed'] = true;
        $result['noRetryConfirmed'] = true;
        $result['claimSha256'] = self::fingerprint(
            $result,
            'claimSha256'
        );
        if (!self::validClaim($result, $authorization)) {
            return self::claimResult('claim_encoding_failed');
        }
        return $result;
    }

    public static function validClaim(
        array $claim,
        array $authorization
    ): bool {
        return array_keys($claim) === array_keys(self::claimResult())
            && ($claim['valid'] ?? null) === true
            && ($claim['status'] ?? null)
                === 'claim_prepared_no_contact_attempt'
            && self::validAuthorization(
                $authorization,
                (int) ($claim['claimedAtEpoch'] ?? 0)
            )
            && self::commonIdentityValid($claim)
            && self::claimMatchesAuthorization($claim, $authorization)
            && self::sha256($claim['claimNonceSha256'] ?? null)
            && !hash_equals(
                $claim['claimNonceSha256'],
                $claim['authorizationNonceSha256']
            )
            && ($claim['attemptNumber'] ?? null) === 1
            && ($claim['maximumAttempts'] ?? null) === 1
            && ($claim['remainingAttempts'] ?? null) === 0
            && ($claim['priorClaimCount'] ?? null) === 0
            && ($claim['oneAttemptConfirmed'] ?? null) === true
            && ($claim['noRetryConfirmed'] ?? null) === true
            && ($claim['durableClaimRequired'] ?? null) === true
            && ($claim['claimPersisted'] ?? null) === false
            && ($claim['replayProtectionActive'] ?? null) === false
            && ($claim['executionAuthorized'] ?? null) === false
            && self::allEffectsFalse($claim)
            && self::sha256($claim['claimSha256'] ?? null)
            && hash_equals(
                $claim['claimSha256'],
                self::fingerprint($claim, 'claimSha256')
            )
            && ($claim['errors'] ?? null) === [];
    }

    public static function projectState(
        array $authorization,
        array $claim,
        array $createEvidence = [],
        array $lookupEvidence = []
    ): array {
        $result = self::stateResult('state_refused');
        if (!self::validClaim($claim, $authorization)
            || ($createEvidence === [] && $lookupEvidence !== [])
        ) {
            $result['errors'] = ['state_refused'];
            return $result;
        }

        $state = 'claim_prepared';
        $providerReference = '';
        $transactionStatus = '';
        $proposedOutcome = '';
        $finalObservation = false;
        $createEvidenceSha256 = '';
        $lookupEvidenceSha256 = '';

        if ($createEvidence !== []) {
            if (!RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::validCreate(
                $createEvidence
            )
                || !hash_equals(
                    $authorization['wireRequestSha256'],
                    $createEvidence['wireRequestSha256']
                )
                || $authorization['orderId'] !== $createEvidence['reference']
                || $authorization['amountMinor']
                    !== $createEvidence['amountMinor']
            ) {
                $result['errors'] = ['state_refused'];
                return $result;
            }
            $state = 'pending_observed';
            $providerReference = $createEvidence['providerReference'];
            $transactionStatus = 'PENDING';
            $proposedOutcome = 'pending';
            $createEvidenceSha256 =
                $createEvidence['createEvidenceSha256'];
        }

        if ($lookupEvidence !== []) {
            if (!RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::validLookup(
                $lookupEvidence
            )
                || !hash_equals(
                    $createEvidence['createEvidenceSha256'],
                    $lookupEvidence['createEvidenceSha256']
                )
                || $providerReference
                    !== $lookupEvidence['providerReference']
                || $authorization['orderId']
                    !== $lookupEvidence['reference']
                || $authorization['amountMinor']
                    !== $lookupEvidence['amountMinor']
            ) {
                $result['errors'] = ['state_refused'];
                return $result;
            }
            $transactionStatus = $lookupEvidence['transactionStatus'];
            $proposedOutcome = $lookupEvidence['proposedOutcome'];
            $finalObservation = $lookupEvidence['final'];
            $state = $transactionStatus === 'PENDING'
                ? 'pending_observed'
                : ($transactionStatus === 'APPROVED'
                    ? 'approved_observed'
                    : 'failed_observed');
            $lookupEvidenceSha256 =
                $lookupEvidence['lookupEvidenceSha256'];
        }

        $result['valid'] = true;
        $result['status'] = 'state_projected_no_contact_attempt';
        foreach ([
            'orderId', 'amountMinor', 'clientScopeSha256', 'databaseSha256',
            'actorSubjectSha256', 'authorizationSha256', 'claimSha256',
            'attemptNumber', 'remainingAttempts',
        ] as $key) {
            $result[$key] = $claim[$key];
        }
        $result['attemptState'] = $state;
        $result['providerReference'] = $providerReference;
        $result['transactionStatus'] = $transactionStatus;
        $result['proposedOutcome'] = $proposedOutcome;
        $result['finalObservation'] = $finalObservation;
        $result['createEvidenceSha256'] = $createEvidenceSha256;
        $result['lookupEvidenceSha256'] = $lookupEvidenceSha256;
        $result['attemptStateSha256'] = self::fingerprint(
            $result,
            'attemptStateSha256'
        );
        if (!self::validState(
            $result,
            $authorization,
            $claim,
            $createEvidence,
            $lookupEvidence
        )) {
            return self::stateResult('state_encoding_failed');
        }
        return $result;
    }

    public static function validState(
        array $state,
        array $authorization,
        array $claim,
        array $createEvidence = [],
        array $lookupEvidence = []
    ): bool {
        if (array_keys($state) !== array_keys(self::stateResult())
            || ($state['valid'] ?? null) !== true
            || ($state['status'] ?? null)
                !== 'state_projected_no_contact_attempt'
            || !self::validClaim($claim, $authorization)
            || !self::commonIdentityValid($state)
            || !hash_equals(
                $state['authorizationSha256'] ?? '',
                $authorization['authorizationSha256'] ?? ''
            )
            || !hash_equals(
                $state['claimSha256'] ?? '',
                $claim['claimSha256'] ?? ''
            )
            || ($state['attemptNumber'] ?? null) !== 1
            || ($state['remainingAttempts'] ?? null) !== 0
            || ($state['durableClaimRequired'] ?? null) !== true
            || ($state['claimPersisted'] ?? null) !== false
            || self::allEffectsFalse($state) !== true
            || !self::sha256($state['attemptStateSha256'] ?? null)
            || !hash_equals(
                $state['attemptStateSha256'],
                self::fingerprint($state, 'attemptStateSha256')
            )
            || ($state['errors'] ?? null) !== []
        ) {
            return false;
        }

        $expected = self::stateResult('state_refused');
        $recomputed = self::projectStateUnchecked(
            $authorization,
            $claim,
            $createEvidence,
            $lookupEvidence
        );
        return $recomputed !== $expected && $state === $recomputed;
    }

    private static function projectStateUnchecked(
        array $authorization,
        array $claim,
        array $createEvidence,
        array $lookupEvidence
    ): array {
        if (!self::validClaim($claim, $authorization)
            || ($createEvidence === [] && $lookupEvidence !== [])
        ) {
            $refused = self::stateResult('state_refused');
            $refused['errors'] = ['state_refused'];
            return $refused;
        }
        $state = self::stateResult();
        $state['valid'] = true;
        $state['status'] = 'state_projected_no_contact_attempt';
        foreach ([
            'orderId', 'amountMinor', 'clientScopeSha256', 'databaseSha256',
            'actorSubjectSha256', 'authorizationSha256', 'claimSha256',
            'attemptNumber', 'remainingAttempts',
        ] as $key) {
            $state[$key] = $claim[$key];
        }
        $state['attemptState'] = 'claim_prepared';
        if ($createEvidence !== []) {
            if (!RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::validCreate(
                $createEvidence
            )
                || !hash_equals(
                    $authorization['wireRequestSha256'],
                    $createEvidence['wireRequestSha256']
                )
                || $authorization['orderId'] !== $createEvidence['reference']
                || $authorization['amountMinor']
                    !== $createEvidence['amountMinor']
            ) {
                $refused = self::stateResult('state_refused');
                $refused['errors'] = ['state_refused'];
                return $refused;
            }
            $state['attemptState'] = 'pending_observed';
            $state['providerReference'] =
                $createEvidence['providerReference'];
            $state['transactionStatus'] = 'PENDING';
            $state['proposedOutcome'] = 'pending';
            $state['createEvidenceSha256'] =
                $createEvidence['createEvidenceSha256'];
        }
        if ($lookupEvidence !== []) {
            if (!RED_CMS_Store_Lite_Wompi_Transaction_Response_Containment::validLookup(
                $lookupEvidence
            )
                || !hash_equals(
                    $createEvidence['createEvidenceSha256'],
                    $lookupEvidence['createEvidenceSha256']
                )
                || $state['providerReference']
                    !== $lookupEvidence['providerReference']
                || $authorization['orderId']
                    !== $lookupEvidence['reference']
                || $authorization['amountMinor']
                    !== $lookupEvidence['amountMinor']
            ) {
                $refused = self::stateResult('state_refused');
                $refused['errors'] = ['state_refused'];
                return $refused;
            }
            $status = $lookupEvidence['transactionStatus'];
            $state['attemptState'] = $status === 'PENDING'
                ? 'pending_observed'
                : ($status === 'APPROVED'
                    ? 'approved_observed'
                    : 'failed_observed');
            $state['transactionStatus'] = $status;
            $state['proposedOutcome'] =
                $lookupEvidence['proposedOutcome'];
            $state['finalObservation'] = $lookupEvidence['final'];
            $state['lookupEvidenceSha256'] =
                $lookupEvidence['lookupEvidenceSha256'];
        }
        $state['attemptStateSha256'] = self::fingerprint(
            $state,
            'attemptStateSha256'
        );
        return $state;
    }

    private static function scopeValid(array $scope, int $nowEpoch): bool
    {
        if (array_keys($scope) !== [
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'secretAvailabilitySha256', 'authorizationNonceSha256',
            'issuedAtEpoch', 'expiresAtEpoch',
            'ownerAuthorityRevalidated', 'orderAuthorityRevalidated',
            'packageEnabled', 'storeEnabled', 'oneAttemptConfirmed',
            'noRetryConfirmed', 'networkDisabledConfirmed',
            'providerContactDenied', 'providerMutationDenied',
            'orderMutationDenied',
        ]) {
            return false;
        }
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'secretAvailabilitySha256', 'authorizationNonceSha256',
        ] as $key) {
            if (!self::sha256($scope[$key] ?? null)) {
                return false;
            }
        }
        foreach ([
            'ownerAuthorityRevalidated', 'orderAuthorityRevalidated',
            'packageEnabled', 'storeEnabled', 'oneAttemptConfirmed',
            'noRetryConfirmed', 'networkDisabledConfirmed',
            'providerContactDenied', 'providerMutationDenied',
            'orderMutationDenied',
        ] as $key) {
            if (($scope[$key] ?? null) !== true) {
                return false;
            }
        }
        $issued = $scope['issuedAtEpoch'] ?? null;
        $expires = $scope['expiresAtEpoch'] ?? null;
        return is_int($issued)
            && is_int($expires)
            && $issued >= 1
            && $issued <= $nowEpoch
            && $nowEpoch < $expires
            && $expires > $issued
            && $expires - $issued <= 900;
    }

    private static function scopeProjectionValid(
        array $authorization,
        int $evaluatedAtEpoch
    ): bool {
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'secretAvailabilitySha256', 'authorizationNonceSha256',
        ] as $key) {
            if (!self::sha256($authorization[$key] ?? null)) {
                return false;
            }
        }
        foreach ([
            'ownerAuthorityRevalidated', 'orderAuthorityRevalidated',
            'packageEnabled', 'storeEnabled', 'oneAttemptConfirmed',
            'noRetryConfirmed', 'networkDisabledConfirmed',
            'providerContactDenied', 'providerMutationDenied',
            'orderMutationDenied',
        ] as $key) {
            if (($authorization[$key] ?? null) !== true) {
                return false;
            }
        }
        $issued = $authorization['issuedAtEpoch'] ?? null;
        $expires = $authorization['expiresAtEpoch'] ?? null;
        return is_int($issued)
            && is_int($expires)
            && $issued >= 1
            && $issued <= $evaluatedAtEpoch
            && $evaluatedAtEpoch < $expires
            && $expires > $issued
            && $expires - $issued <= 900;
    }

    private static function claimMatchesAuthorization(
        array $claim,
        array $authorization
    ): bool {
        foreach ([
            'orderId', 'amountMinor', 'clientScopeSha256', 'databaseSha256',
            'actorSubjectSha256', 'authorizationNonceSha256',
            'planSha256', 'wireRequestSha256', 'wireEvidenceSha256',
            'issuedAtEpoch', 'expiresAtEpoch', 'maximumAttempts',
        ] as $key) {
            if (($claim[$key] ?? null) !== ($authorization[$key] ?? null)) {
                return false;
            }
        }
        return hash_equals(
            $claim['authorizationSha256'] ?? '',
            $authorization['authorizationSha256'] ?? ''
        )
            && ($claim['claimedAtEpoch'] ?? 0)
                >= $authorization['issuedAtEpoch']
            && ($claim['claimedAtEpoch'] ?? 0)
                < $authorization['expiresAtEpoch'];
    }

    private static function commonIdentityValid(array $value): bool
    {
        return ($value['packageId'] ?? null)
                === 'redcms.store-lite-wompi'
            && ($value['packageVersion'] ?? null) === '0.1.4'
            && ($value['storePackageId'] ?? null) === 'redcms.store-lite'
            && ($value['minimumStoreVersion'] ?? null) === '0.1.35'
            && ($value['provider'] ?? null) === 'wompi'
            && ($value['method'] ?? null) === 'nequi'
            && ($value['environment'] ?? null) === 'sandbox'
            && ($value['operation'] ?? null)
                === 'checkout.create-sandbox-no-contact'
            && ($value['transportMode'] ?? null) === 'sealed_double_only'
            && is_string($value['orderId'] ?? null)
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $value['orderId']
            ) === 1
            && is_int($value['amountMinor'] ?? null)
            && $value['amountMinor'] >= 100
            && $value['amountMinor'] <= 999999999999
            && ($value['currency'] ?? null) === 'COP';
    }

    private static function allEffectsFalse(array $value): bool
    {
        foreach ([
            'providerContactAuthorized', 'providerMutationAuthorized',
            'orderMutationAuthorized', 'executionStarted',
            'executionPerformed', 'secretResolution', 'networkAccess',
            'providerContact', 'providerMutation', 'paymentVerified',
            'eventAgreement', 'paymentApplied', 'orderMutation',
            'retryAuthorized',
        ] as $key) {
            if (($value[$key] ?? null) !== false) {
                return false;
            }
        }
        return true;
    }

    private static function authorizationResult(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'packageId' => 'redcms.store-lite-wompi',
            'packageVersion' => '0.1.4',
            'storePackageId' => 'redcms.store-lite',
            'minimumStoreVersion' => '0.1.35',
            'provider' => 'wompi',
            'method' => 'nequi',
            'environment' => 'sandbox',
            'operation' => 'checkout.create-sandbox-no-contact',
            'transportMode' => 'sealed_double_only',
            'orderId' => '',
            'amountMinor' => 0,
            'currency' => 'COP',
            'clientScopeSha256' => '',
            'databaseSha256' => '',
            'actorSubjectSha256' => '',
            'secretAvailabilitySha256' => '',
            'authorizationNonceSha256' => '',
            'planSha256' => '',
            'requestEvidenceSha256' => '',
            'wireRequestSha256' => '',
            'wireEvidenceSha256' => '',
            'consentEvidenceSha256' => '',
            'issuedAtEpoch' => 0,
            'expiresAtEpoch' => 0,
            'maximumAttempts' => 0,
            'ownerAuthorityRevalidated' => false,
            'orderAuthorityRevalidated' => false,
            'packageEnabled' => false,
            'storeEnabled' => false,
            'oneAttemptConfirmed' => false,
            'noRetryConfirmed' => false,
            'networkDisabledConfirmed' => false,
            'providerContactDenied' => false,
            'providerMutationDenied' => false,
            'orderMutationDenied' => false,
            'noContactAttemptAuthorized' => false,
            'providerContactAuthorized' => false,
            'providerMutationAuthorized' => false,
            'orderMutationAuthorized' => false,
            'durableClaimRequired' => true,
            'authorizationPersisted' => false,
            'claimPersisted' => false,
            'executionStarted' => false,
            'executionPerformed' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'paymentVerified' => false,
            'eventAgreement' => false,
            'paymentApplied' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'authorizationSha256' => '',
            'errors' => [],
        ];
    }

    private static function claimResult(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'packageId' => 'redcms.store-lite-wompi',
            'packageVersion' => '0.1.4',
            'storePackageId' => 'redcms.store-lite',
            'minimumStoreVersion' => '0.1.35',
            'provider' => 'wompi',
            'method' => 'nequi',
            'environment' => 'sandbox',
            'operation' => 'checkout.create-sandbox-no-contact',
            'transportMode' => 'sealed_double_only',
            'orderId' => '',
            'amountMinor' => 0,
            'currency' => 'COP',
            'clientScopeSha256' => '',
            'databaseSha256' => '',
            'actorSubjectSha256' => '',
            'authorizationSha256' => '',
            'authorizationNonceSha256' => '',
            'claimNonceSha256' => '',
            'planSha256' => '',
            'wireRequestSha256' => '',
            'wireEvidenceSha256' => '',
            'issuedAtEpoch' => 0,
            'expiresAtEpoch' => 0,
            'claimedAtEpoch' => 0,
            'attemptNumber' => 0,
            'maximumAttempts' => 0,
            'remainingAttempts' => 0,
            'priorClaimCount' => 0,
            'oneAttemptConfirmed' => false,
            'noRetryConfirmed' => false,
            'durableClaimRequired' => true,
            'claimPersisted' => false,
            'replayProtectionActive' => false,
            'executionAuthorized' => false,
            'providerContactAuthorized' => false,
            'providerMutationAuthorized' => false,
            'orderMutationAuthorized' => false,
            'executionStarted' => false,
            'executionPerformed' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'paymentVerified' => false,
            'eventAgreement' => false,
            'paymentApplied' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'claimSha256' => '',
            'errors' => [],
        ];
    }

    private static function stateResult(string $status = 'invalid'): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'packageId' => 'redcms.store-lite-wompi',
            'packageVersion' => '0.1.4',
            'storePackageId' => 'redcms.store-lite',
            'minimumStoreVersion' => '0.1.35',
            'provider' => 'wompi',
            'method' => 'nequi',
            'environment' => 'sandbox',
            'operation' => 'checkout.create-sandbox-no-contact',
            'transportMode' => 'sealed_double_only',
            'orderId' => '',
            'amountMinor' => 0,
            'currency' => 'COP',
            'clientScopeSha256' => '',
            'databaseSha256' => '',
            'actorSubjectSha256' => '',
            'authorizationSha256' => '',
            'claimSha256' => '',
            'attemptNumber' => 0,
            'remainingAttempts' => 0,
            'attemptState' => '',
            'providerReference' => '',
            'transactionStatus' => '',
            'proposedOutcome' => '',
            'finalObservation' => false,
            'createEvidenceSha256' => '',
            'lookupEvidenceSha256' => '',
            'durableClaimRequired' => true,
            'claimPersisted' => false,
            'providerContactAuthorized' => false,
            'providerMutationAuthorized' => false,
            'orderMutationAuthorized' => false,
            'executionStarted' => false,
            'executionPerformed' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'paymentVerified' => false,
            'eventAgreement' => false,
            'paymentApplied' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'attemptStateSha256' => '',
            'errors' => [],
        ];
    }

    private static function sha256($value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    private static function hash(array $value): string
    {
        try {
            $encoded = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return '';
        }
        return hash('sha256', $encoded);
    }

    private static function fingerprint(array $result, string $hashKey): string
    {
        unset($result['valid'], $result[$hashKey]);
        return self::hash($result);
    }
}

?>
