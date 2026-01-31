--
-- schema.sql — Database schema for linkhill (v0.1).
-- Project: linkhill
-- SPDX-License-Identifier: MIT
-- Copyright (c) 2026 Hillwork, LLC
--

CREATE TABLE users (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email                  VARCHAR(190) NOT NULL UNIQUE,
  username               VARCHAR(32)  NOT NULL UNIQUE,
  display_name           VARCHAR(80)  NOT NULL,
  password_hash          VARCHAR(255) NOT NULL,
  password_updated_at    DATETIME NULL,
  webauthn_user_handle   VARBINARY(32) NULL UNIQUE,
  last_login_at          DATETIME NULL,
  user_session_version   INT UNSIGNED NOT NULL DEFAULT 0,
  role                   ENUM('admin','user') NOT NULL DEFAULT 'user',
  mfa_enabled            TINYINT(1) NOT NULL DEFAULT 0,
  mfa_secret             VARCHAR(64) NULL,
  avatar_path            VARCHAR(255) NULL,
  theme                  ENUM('light','dark','custom') NOT NULL DEFAULT 'light',
  bio                    TEXT NULL,
  custom_footer          TEXT NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  email_verified_at      DATETIME NULL,
  updated_at             DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE links (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  entry_type  ENUM('link','heading') NOT NULL DEFAULT 'link',
  title       VARCHAR(80) NOT NULL,
  url         VARCHAR(2000) NULL,
  description TEXT NULL,
  color_hex   CHAR(7) NOT NULL DEFAULT '#111827',
  icon_slug   VARCHAR(40) NULL,
  position    INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE link_clicks (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  link_id     INT UNSIGNED NOT NULL,
  clicked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash     CHAR(64) NULL,
  ua_hash     CHAR(64) NULL,
  FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE,
  INDEX (link_id, clicked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  VARBINARY(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_expires (user_id, expires_at),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE webauthn_credentials (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             INT UNSIGNED NOT NULL,
  credential_id       VARBINARY(255) NOT NULL UNIQUE,
  public_key          TEXT NOT NULL,
  sign_count          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  aaguid              BINARY(16) NULL,
  transports          VARCHAR(255) NULL,
  attestation_format  VARCHAR(64) NULL,
  nickname            VARCHAR(100) NULL,
  backup_eligible     TINYINT(1) NOT NULL DEFAULT 0,
  backup_state        TINYINT(1) NOT NULL DEFAULT 0,
  uv_initialized      TINYINT(1) NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at        DATETIME NULL,
  INDEX idx_wc_user (user_id),
  CONSTRAINT fk_wc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE email_verifications (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  VARBINARY(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_expires (user_id, expires_at),
  CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
