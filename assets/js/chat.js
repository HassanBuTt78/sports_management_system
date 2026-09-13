/* ============================================================
   chat.js — Module 10 Communication & Chat System
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {
  var BASE = window.SMS_BASE_URL || '';
  var CSRF = window.SMS_CSRF_TOKEN || '';
  var EMOJIS = ['😀','😂','😍','👍','🙏','🎉','🔥','⚽','🏏','🏐','🏁','🏆','😢','😮','❤️','👏'];

  function post(url, params) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(params) })
      .then(function (r) { return r.json(); });
  }
  function postForm(url, formData) {
    return fetch(url, { method: 'POST', body: formData }).then(function (r) { return r.json(); });
  }

  /* ============ CHAT WINDOW (index.php) ============ */
  var messagesBox = document.getElementById('chatMessages');
  var composerForm = document.getElementById('chatComposerForm');

  if (messagesBox && composerForm) {
    messagesBox.scrollTop = messagesBox.scrollHeight;
    var convId = window.SMS_ACTIVE_CONVERSATION;
    var lastMessageId = window.SMS_LAST_MESSAGE_ID || 0;
    var replyToId = document.getElementById('chatReplyToId');
    var replyBar = document.getElementById('chatReplyBar');
    var replyText = document.getElementById('chatReplyText');
    var attachInput = document.getElementById('chatAttachInput');
    var attachPreview = document.getElementById('chatAttachPreview');
    var pendingFile = null;

    post(BASE + '/chat/mark_read.php', { conversation_id: convId, csrf_token: CSRF });

    /* ---- attachment pick + client-side preview + server validation ---- */
    document.getElementById('chatAttachBtn').addEventListener('click', function () { attachInput.click(); });
    attachInput.addEventListener('change', function () {
      var file = attachInput.files[0];
      if (!file) return;
      var fd = new FormData();
      fd.append('attachment', file);
      fd.append('csrf_token', CSRF);
      postForm(BASE + '/chat/upload_attachment.php', fd).then(function (data) {
        if (!data.success) {
          Swal.fire({ icon: 'error', title: 'File type is not supported', text: data.message || 'File size is too large.' });
          attachInput.value = '';
          return;
        }
        pendingFile = file;
        attachPreview.classList.remove('d-none');
        attachPreview.innerHTML = '<i class="fa-solid fa-paperclip"></i> ' + file.name + ' (' + Math.round(file.size / 1024) + ' KB) <button type="button" id="chatAttachRemove" class="btn btn-sm btn-link text-danger">Remove</button>';
        document.getElementById('chatAttachRemove').addEventListener('click', function () {
          pendingFile = null; attachInput.value = ''; attachPreview.classList.add('d-none'); attachPreview.innerHTML = '';
        });
      });
    });

    /* ---- emoji picker ---- */
    var emojiBtn = document.getElementById('chatEmojiBtn');
    var emojiPicker = document.getElementById('chatEmojiPicker');
    if (!emojiPicker.dataset.built) {
      EMOJIS.forEach(function (e) {
        var span = document.createElement('span');
        span.textContent = e;
        span.addEventListener('click', function () {
          document.getElementById('chatMessageInput').value += e;
          emojiPicker.classList.add('d-none');
        });
        emojiPicker.appendChild(span);
      });
      emojiPicker.dataset.built = '1';
    }
    emojiBtn.addEventListener('click', function () { emojiPicker.classList.toggle('d-none'); });

    /* ---- reply ---- */
    function setReply(messageId, text) {
      replyToId.value = messageId;
      replyText.textContent = 'Replying to: ' + (text || 'attachment');
      replyBar.classList.remove('d-none');
    }
    document.getElementById('chatReplyCancel').addEventListener('click', function () {
      replyToId.value = ''; replyBar.classList.add('d-none');
    });

    /* ---- send ---- */
    composerForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(composerForm);
      if (pendingFile) fd.set('attachment', pendingFile);
      var textVal = document.getElementById('chatMessageInput').value.trim();
      if (!textVal && !pendingFile) return;

      postForm(BASE + '/chat/send_message.php', fd).then(function (data) {
        if (data.success) {
          document.getElementById('chatMessageInput').value = '';
          pendingFile = null; attachInput.value = ''; attachPreview.classList.add('d-none'); attachPreview.innerHTML = '';
          replyToId.value = ''; replyBar.classList.add('d-none');
          pollMessages();
        } else {
          Swal.fire({ icon: 'error', title: 'Message could not be sent.', text: data.message || '' });
        }
      });
    });

    /* ---- message actions: reply / edit / delete / copy ---- */
    messagesBox.addEventListener('click', function (e) {
      var btn = e.target.closest('.chat-msg-action');
      if (!btn) return;
      var row = btn.closest('.chat-bubble-row');
      var messageId = row.getAttribute('data-message-id');
      var action = btn.getAttribute('data-action');
      var textEl = row.querySelector('.chat-bubble-text');

      if (action === 'reply') {
        setReply(messageId, textEl ? textEl.textContent : null);
      } else if (action === 'copy') {
        if (textEl) navigator.clipboard.writeText(textEl.textContent);
      } else if (action === 'edit') {
        var current = textEl ? textEl.textContent : '';
        Swal.fire({ title: 'Edit message', input: 'text', inputValue: current, showCancelButton: true }).then(function (res) {
          if (res.isConfirmed && res.value.trim()) {
            post(BASE + '/chat/edit_message.php', { message_id: messageId, message: res.value.trim(), csrf_token: CSRF }).then(function (data) {
              if (data.success) { if (textEl) textEl.textContent = res.value.trim(); }
              else Swal.fire({ icon: 'error', title: 'Could not edit', text: data.message || '' });
            });
          }
        });
      } else if (action === 'delete') {
        Swal.fire({ title: 'Delete this message?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#c1443c' }).then(function (res) {
          if (res.isConfirmed) {
            post(BASE + '/chat/delete_message.php', { message_id: messageId, csrf_token: CSRF }).then(function (data) {
              if (data.success) row.querySelector('.chat-bubble').innerHTML = '<em class="text-muted">This message was deleted.</em>';
              else Swal.fire({ icon: 'error', title: 'Could not delete', text: data.message || '' });
            });
          }
        });
      }
    });

    /* ---- polling: new messages every 4s ---- */
    function renderIncoming(m) {
      var row = document.createElement('div');
      row.className = 'chat-bubble-row' + (m.is_mine ? ' mine' : '');
      row.setAttribute('data-message-id', m.message_id);
      var html = '<div class="chat-bubble">';
      if (m.is_deleted) {
        html += '<em class="text-muted">This message was deleted.</em>';
      } else {
        if (m.reply_message) html += '<div class="chat-reply-preview">' + escapeHtml(m.reply_message.slice(0, 60)) + '</div>';
        if (m.attachment && m.attachment_type === 'image') html += '<img src="' + m.download_url + '" class="chat-attachment-image">';
        else if (m.attachment && m.attachment_type === 'video') html += '<video controls class="chat-attachment-video"><source src="' + m.download_url + '"></video>';
        else if (m.attachment) html += '<a href="' + m.download_url + '" class="chat-attachment-doc"><i class="fa-solid fa-file-lines"></i>Attachment</a>';
        if (m.message) html += '<div class="chat-bubble-text">' + escapeHtml(m.message) + '</div>';
      }
      html += '<div class="chat-bubble-meta"><span>' + m.sent_at + '</span></div></div>';
      row.innerHTML = html;
      messagesBox.appendChild(row);
    }
    function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function pollMessages() {
      fetch(BASE + '/chat/load_messages.php?conversation_id=' + convId + '&after_id=' + lastMessageId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success && data.messages.length) {
            data.messages.forEach(function (m) { renderIncoming(m); lastMessageId = m.message_id; });
            messagesBox.scrollTop = messagesBox.scrollHeight;
            post(BASE + '/chat/mark_read.php', { conversation_id: convId, csrf_token: CSRF });
          }
        });
    }
    setInterval(pollMessages, 4000);
  }

  /* ============ NEW MESSAGE MODAL ============ */
  var newMessageBtn = document.getElementById('newMessageBtn');
  var newMessageModal = document.getElementById('newMessageModal');
  if (newMessageBtn && newMessageModal) {
    var resultsBox = document.getElementById('newMessageResults');
    var searchInput = document.getElementById('newMessageSearch');

    function loadContacts(q) {
      fetch(BASE + '/chat/search_users.php?q=' + encodeURIComponent(q || ''))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var contacts = (data.results && data.results.contacts) || [];
          resultsBox.innerHTML = contacts.length ? '' : '<p class="text-muted text-center py-3">No matching contacts.</p>';
          contacts.forEach(function (c) {
            var item = document.createElement('div');
            item.className = 'chat-contact-item';
            item.innerHTML = '<img src="' + c.image_url + '"><span>' + c.name + ' <small class="text-muted">(' + c.role + ')</small></span>';
            item.addEventListener('click', function () {
              post(BASE + '/chat/new_conversation.php', { target_role: c.role, target_id: c.id, csrf_token: CSRF }).then(function (data) {
                if (data.success) window.location.href = BASE + '/chat/index.php?id=' + data.conversation_id;
                else Swal.fire({ icon: 'error', title: 'Not authorized', text: data.message || '' });
              });
            });
            resultsBox.appendChild(item);
          });
        });
    }

    newMessageBtn.addEventListener('click', function () { newMessageModal.classList.remove('d-none'); loadContacts(''); });
    document.getElementById('newMessageClose').addEventListener('click', function () { newMessageModal.classList.add('d-none'); });
    searchInput.addEventListener('input', function () { loadContacts(searchInput.value); });
  }

  /* ============ SIDEBAR CONVERSATION SEARCH ============ */
  var chatSearchInput = document.getElementById('chatSearchInput');
  if (chatSearchInput) {
    chatSearchInput.addEventListener('input', function () {
      var q = chatSearchInput.value.toLowerCase();
      document.querySelectorAll('.chat-conv-item').forEach(function (item) {
        var name = item.querySelector('.chat-conv-name').textContent.toLowerCase();
        item.style.display = name.includes(q) ? '' : 'none';
      });
    });
  }

  /* ============ NOTIFICATIONS PAGE ============ */
  document.querySelectorAll('.notif-read-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('.notif-row');
      post(BASE + '/notifications/mark_read.php', { notification_id: row.getAttribute('data-id'), action: 'read', csrf_token: CSRF }).then(function (data) {
        if (data.success) { row.classList.remove('notif-unread'); btn.remove(); }
      });
    });
  });
  document.querySelectorAll('.notif-delete-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('.notif-row');
      post(BASE + '/notifications/mark_read.php', { notification_id: row.getAttribute('data-id'), action: 'delete', csrf_token: CSRF }).then(function (data) {
        if (data.success) row.remove();
        else Swal.fire({ icon: 'error', title: 'Could not delete', text: data.message || '' });
      });
    });
  });
  var markAllBtn = document.getElementById('markAllReadBtn');
  if (markAllBtn) {
    markAllBtn.addEventListener('click', function () {
      post(BASE + '/notifications/mark_all_read.php', { csrf_token: CSRF }).then(function (data) {
        if (data.success) window.location.reload();
      });
    });
  }

  /* ============ GROUP SETTINGS PAGE ============ */
  var addMembersBtn = document.getElementById('addMembersBtn');
  if (addMembersBtn) {
    addMembersBtn.addEventListener('click', function () {
      var checked = Array.from(document.querySelectorAll('#addMembersForm input[type=checkbox]:checked')).map(function (c) { return c.value; });
      if (!checked.length) return;
      var fd = new URLSearchParams();
      fd.append('conversation_id', window.SMS_CONVERSATION_ID);
      fd.append('action', 'add');
      fd.append('csrf_token', CSRF);
      checked.forEach(function (v) { fd.append('members[]', v); });
      fetch(BASE + '/chat/group_members.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.success) window.location.reload();
        else Swal.fire({ icon: 'error', title: 'Could not add members', text: data.message || '' });
      });
    });
  }
  document.querySelectorAll('.remove-member-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var name = btn.getAttribute('data-name');
      Swal.fire({ title: 'Remove ' + name + '?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#c1443c' }).then(function (res) {
        if (res.isConfirmed) {
          post(BASE + '/chat/group_members.php', {
            conversation_id: window.SMS_CONVERSATION_ID, action: 'remove',
            target_role: btn.getAttribute('data-role'), target_id: btn.getAttribute('data-id'), csrf_token: CSRF,
          }).then(function (data) {
            if (data.success) window.location.reload();
            else Swal.fire({ icon: 'error', title: 'Could not remove', text: data.message || '' });
          });
        }
      });
    });
  });
  var deleteGroupBtn = document.getElementById('deleteGroupBtn');
  if (deleteGroupBtn) {
    deleteGroupBtn.addEventListener('click', function () {
      Swal.fire({ title: 'Delete this group?', text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#c1443c' }).then(function (res) {
        if (res.isConfirmed) document.getElementById('deleteGroupForm').submit();
      });
    });
  }
});
