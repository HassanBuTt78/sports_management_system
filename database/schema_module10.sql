-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — MODULE 10 ADDITIVE MIGRATION
--  Communication & Chat System
--  ----------------------------------------------------------------
--  DESIGN NOTE: the brief's suggested schema lists `conversations`
--  AND separate `chat_groups`/`group_members` tables. Since a group
--  is really just a conversation with >2 members, this migration
--  uses ONE unified model — `conversations` (conversation_type:
--  private/group/event/match) + `conversation_members` — to avoid
--  the duplicate-table pattern the brief itself warns against.
--  Group name/description/image live directly on `conversations`
--  (only populated when conversation_type = 'group').
--
--  `messages` (Module 2) already has sender/receiver role+id, a
--  single attachment+type, sent_at, and seen_status — all reused
--  as-is. This only ADDS the columns Module 10 genuinely needs on
--  top: conversation_id, message_type, edit/delete flags, delivered/
--  seen timestamps, and a reply reference.
--
--  `notifications` (Module 2) already supports every notification
--  type this module needs (broadcast via receiver_id = NULL) — no
--  changes needed there at all.
--
--  Import AFTER schema.sql through schema_module9.sql:
--    mysql -u root -p sports_management_system < schema_module10.sql
-- ================================================================

USE sports_management_system;

-- ================================================================
-- conversations
-- ================================================================
CREATE TABLE IF NOT EXISTS conversations (
    conversation_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_type  ENUM('private','group','event','match') NOT NULL DEFAULT 'private',
    title              VARCHAR(150) DEFAULT NULL COMMENT 'Group/event/match display name; NULL for private chats',
    description        VARCHAR(255) DEFAULT NULL,
    image              VARCHAR(255) DEFAULT NULL COMMENT 'Group photo',
    event_id           INT UNSIGNED DEFAULT NULL,
    match_id           INT UNSIGNED DEFAULT NULL,
    created_by_role    ENUM('admin','coach','player') NOT NULL,
    created_by_id      INT UNSIGNED NOT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_conv_event (event_id),
    KEY idx_conv_match (match_id),
    CONSTRAINT fk_conv_event FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_conv_match FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- conversation_members
-- ================================================================
CREATE TABLE IF NOT EXISTS conversation_members (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    user_role       ENUM('admin','coach','player') NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    is_admin        TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Can rename/add/remove members in a group',
    joined_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_read_at    TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_conv_member (conversation_id, user_role, user_id),
    KEY idx_member_user (user_role, user_id),
    CONSTRAINT fk_member_conv FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- messages — additive columns only; all Module 2 columns untouched
-- ================================================================
ALTER TABLE messages
    ADD COLUMN conversation_id     INT UNSIGNED DEFAULT NULL AFTER message_id,
    ADD COLUMN message_type        ENUM('text','image','video','document') NOT NULL DEFAULT 'text' AFTER message,
    ADD COLUMN reply_to_message_id INT UNSIGNED DEFAULT NULL AFTER message_type,
    ADD COLUMN is_edited           TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN is_deleted          TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN delivered_at        TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN seen_at             TIMESTAMP NULL DEFAULT NULL,
    ADD KEY idx_msg_conversation (conversation_id),
    ADD CONSTRAINT fk_msg_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT fk_msg_reply FOREIGN KEY (reply_to_message_id) REFERENCES messages(message_id) ON DELETE SET NULL ON UPDATE CASCADE;

-- ================================================================
-- user_presence — online/offline + last seen, updated via AJAX ping
-- ================================================================
CREATE TABLE IF NOT EXISTS user_presence (
    user_role   ENUM('admin','coach','player') NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    last_seen   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_role, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
