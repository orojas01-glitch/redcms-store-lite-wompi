CREATE TABLE IF NOT EXISTS `RED_Addon_StoreLite_Wompi_Payment_Attempts` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ClientScopeSHA256` binary(32) NOT NULL,
  `OrderID` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `OrderSnapshotSHA256` binary(32) NOT NULL,
  `IdempotencySHA256` binary(32) NOT NULL,
  `ProviderTransactionRef` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `AmountMinor` bigint unsigned NOT NULL,
  `Currency` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `PaymentMethod` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `AttemptStatus` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `RequestEvidenceSHA256` binary(32) NOT NULL,
  `AcceptanceEvidenceSHA256` binary(32) NOT NULL,
  `CreatedAt` int unsigned NOT NULL,
  `RecordedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_wompi_attempt_idempotency` (
    `ClientScopeSHA256`, `OrderID`, `IdempotencySHA256`
  ),
  UNIQUE KEY `uq_wompi_attempt_transaction` (
    `ClientScopeSHA256`, `ProviderTransactionRef`
  ),
  KEY `idx_wompi_attempt_order` (`ClientScopeSHA256`, `OrderID`, `RecordID`),
  CONSTRAINT `chk_wompi_attempt_order_id` CHECK (
    `OrderID` REGEXP '^ord_[a-f0-9]{32}$'
  ),
  CONSTRAINT `chk_wompi_attempt_transaction_ref` CHECK (
    `ProviderTransactionRef` REGEXP '^[A-Za-z0-9][A-Za-z0-9._:-]{0,159}$'
  ),
  CONSTRAINT `chk_wompi_attempt_amount` CHECK (
    `AmountMinor` BETWEEN 100 AND 999999999999
  ),
  CONSTRAINT `chk_wompi_attempt_currency` CHECK (`Currency` = 'COP'),
  CONSTRAINT `chk_wompi_attempt_method` CHECK (`PaymentMethod` = 'NEQUI'),
  CONSTRAINT `chk_wompi_attempt_status` CHECK (`AttemptStatus` = 'pending'),
  CONSTRAINT `chk_wompi_attempt_created` CHECK (
    `CreatedAt` BETWEEN 1 AND 4102444800
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
