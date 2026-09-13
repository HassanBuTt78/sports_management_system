-- ================================================================
--  ONE-TIME DATA REPAIR — chat bind_param bug
--  ----------------------------------------------------------------
--  A bind_param type mismatch in getOrCreatePrivateConversation()
--  caused one participant of every 1-1 chat created before this fix
--  to be saved with an EMPTY user_role (MySQL's ENUM behavior when
--  an invalid numeric index is written to it). This deletes those
--  broken rows and any private conversation left with fewer than 2
--  members as a result, so affected users can just start the
--  conversation again — no messages are lost for conversations that
--  weren't affected.
--
--  Run this ONCE in phpMyAdmin's SQL tab, after replacing your
--  project files with the fixed ones.
-- ================================================================

USE sports_management_system;

DELETE FROM conversation_members WHERE user_role = '' OR user_role IS NULL;

DELETE FROM conversations
WHERE conversation_type = 'private'
  AND conversation_id NOT IN (
      SELECT conversation_id FROM (
          SELECT conversation_id FROM conversation_members
          GROUP BY conversation_id HAVING COUNT(*) >= 2
      ) AS ok_conversations
  );
