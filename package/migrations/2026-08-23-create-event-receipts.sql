CREATE TABLE IF NOT EXISTS `RED_Addon_StoreLite_Wompi_Event_Receipts` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `AttemptRecordID` bigint unsigned NOT NULL,
  `ClientScopeSHA256` binary(32) NOT NULL,
  `EventEvidenceSHA256` binary(32) NOT NULL,
  `ProviderTransactionRef` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `OrderID` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `OrderSnapshotSHA256` binary(32) NOT NULL,
  `ProviderEnvironment` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ProviderStatus` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `NormalizedOutcome` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `AmountMinor` bigint unsigned NOT NULL,
  `Currency` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `PaymentMethod` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `VerificationSource` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ReplayStatus` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ProcessingStatus` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `OccurredAt` int unsigned NOT NULL,
  `ReceivedAt` int unsigned NOT NULL,
  `RecordedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_wompi_event_evidence` (
    `ClientScopeSHA256`, `EventEvidenceSHA256`
  ),
  KEY `idx_wompi_event_attempt` (`AttemptRecordID`, `RecordID`),
  KEY `idx_wompi_event_order` (`ClientScopeSHA256`, `OrderID`, `RecordID`),
  CONSTRAINT `fk_wompi_event_attempt` FOREIGN KEY (`AttemptRecordID`)
    REFERENCES `RED_Addon_StoreLite_Wompi_Payment_Attempts` (`RecordID`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_wompi_event_transaction_ref` CHECK (
    `ProviderTransactionRef` REGEXP '^[A-Za-z0-9][A-Za-z0-9._:-]{0,159}$'
  ),
  CONSTRAINT `chk_wompi_event_order_id` CHECK (
    `OrderID` REGEXP '^ord_[a-f0-9]{32}$'
  ),
  CONSTRAINT `chk_wompi_event_environment` CHECK (
    `ProviderEnvironment` = 'sandbox'
  ),
  CONSTRAINT `chk_wompi_event_projection` CHECK (
    (`ProviderStatus` = 'APPROVED' AND `NormalizedOutcome` = 'paid')
    OR
    (`ProviderStatus` IN ('DECLINED', 'ERROR')
      AND `NormalizedOutcome` = 'failed')
  ),
  CONSTRAINT `chk_wompi_event_amount` CHECK (
    `AmountMinor` BETWEEN 100 AND 999999999999
  ),
  CONSTRAINT `chk_wompi_event_currency` CHECK (`Currency` = 'COP'),
  CONSTRAINT `chk_wompi_event_method` CHECK (`PaymentMethod` = 'NEQUI'),
  CONSTRAINT `chk_wompi_event_source` CHECK (
    `VerificationSource` = 'signed_event_and_lookup'
  ),
  CONSTRAINT `chk_wompi_event_state` CHECK (
    `ReplayStatus` = 'unseen' AND `ProcessingStatus` = 'normalized'
  ),
  CONSTRAINT `chk_wompi_event_times` CHECK (
    `OccurredAt` BETWEEN 1 AND 4102444800
    AND `ReceivedAt` BETWEEN `OccurredAt` AND `OccurredAt` + 90000
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
