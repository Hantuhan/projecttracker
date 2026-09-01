(function () {
  const modal = document.getElementById('task-modal');
  const form = document.getElementById('task-form');
  if (!modal || !form) return;

  const titleEl = document.getElementById('task-modal-title');
  const deleteBtn = document.getElementById('task-delete');
  const subtasksBlock = document.getElementById('subtasks-block');
  const subtaskList = document.getElementById('subtask-list');
  const subtaskProgress = document.getElementById('subtask-progress');
  const subtaskInput = document.getElementById('subtask-input');
  const subtaskAddBtn = document.getElementById('subtask-add-btn');
  const subtaskHint = document.getElementById('subtask-hint');
  const commentsBlock = document.getElementById('comments-block');
  const commentList = document.getElementById('comment-list');
  const commentInput = document.getElementById('comment-input');
  const commentAddBtn = document.getElementById('comment-add-btn');
  const commentHint = document.getElementById('comment-hint');
  let suppressClick = false;
  let currentSubtasks = [];
  let currentComments = [];
  let dirtyAfterCreate = false;

  function openModal() {
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeModal(forceReload) {
    modal.hidden = true;
    document.body.style.overflow = '';
    form.reset();
    document.getElementById('task-id').value = '';
    deleteBtn.hidden = true;
    titleEl.textContent = 'Task';
    currentSubtasks = [];
    currentComments = [];
    renderSubtasks();
    renderComments();
    subtasksBlock.hidden = true;
    commentsBlock.hidden = true;
    if (forceReload || dirtyAfterCreate) {
      dirtyAfterCreate = false;
      location.reload();
    }
  }

  function renderSubtasks() {
    const total = currentSubtasks.length;
    const done = currentSubtasks.filter((s) => Number(s.is_done) === 1).length;
    subtaskProgress.textContent = total ? `(${done}/${total})` : '';
    subtaskList.innerHTML = '';
    currentSubtasks.forEach((s) => {
      const li = document.createElement('li');
      li.className = 'subtask-item' + (Number(s.is_done) === 1 ? ' done' : '');
      li.innerHTML =
        '<label class="check">' +
        '<input type="checkbox" data-toggle-subtask="' + s.id + '"' + (Number(s.is_done) === 1 ? ' checked' : '') + '>' +
        '<span></span></label>' +
        '<span class="subtask-title"></span>' +
        '<button type="button" class="icon-btn subtask-del" data-del-subtask="' + s.id + '" aria-label="Remove">&times;</button>';
      li.querySelector('.subtask-title').textContent = s.title;
      subtaskList.appendChild(li);
    });
  }

  function renderComments() {
    commentList.innerHTML = '';
    if (!currentComments.length) {
      commentList.innerHTML = '<li class="muted tiny">No comments yet.</li>';
      return;
    }
    const me = window.CURRENT_USER || {};
    currentComments.forEach((c) => {
      const li = document.createElement('li');
      li.className = 'comment-item';
      const when = (c.created_at || '').slice(0, 16);
      const canDelete = me.role === 'admin' || Number(c.user_id) === Number(me.id);
      li.innerHTML =
        '<div class="comment-meta"><strong></strong><span class="muted tiny"></span>' +
        (canDelete
          ? '<button type="button" class="icon-btn comment-del" data-del-comment="' + c.id + '" aria-label="Delete">&times;</button>'
          : '') +
        '</div><p class="comment-body"></p>';
      li.querySelector('strong').textContent = c.user_name || 'User';
      li.querySelector('.muted').textContent = when;
      li.querySelector('.comment-body').textContent = c.body;
      commentList.appendChild(li);
    });
  }

  function setExtrasEnabled(enabled) {
    subtaskInput.disabled = !enabled;
    subtaskAddBtn.disabled = !enabled;
    subtaskHint.hidden = enabled;
    commentInput.disabled = !enabled;
    commentAddBtn.disabled = !enabled;
    commentHint.hidden = enabled;
  }

  function fillForm(task) {
    document.getElementById('task-id').value = task.id || '';
    document.getElementById('task-title').value = task.title || '';
    document.getElementById('task-description').value = task.description || '';
    if (task.project_id) document.getElementById('task-project').value = task.project_id;
    document.getElementById('task-status').value = task.status || 'todo';
    document.getElementById('task-priority').value = task.priority || 'medium';
    document.getElementById('task-assignee').value = task.assignee_id || '';
    document.getElementById('task-due').value = task.due_date || '';
    deleteBtn.hidden = !task.id;
    titleEl.textContent = task.id ? 'Edit task' : 'New task';
    currentSubtasks = task.subtasks || [];
    currentComments = task.comments || [];
    subtasksBlock.hidden = false;
    commentsBlock.hidden = false;
    setExtrasEnabled(!!task.id);
    renderSubtasks();
    renderComments();
  }

  async function editTask(id) {
    const data = await api('api/tasks.php?id=' + encodeURIComponent(id));
    fillForm(data.task);
    openModal();
  }

  async function addSubtask() {
    const taskId = document.getElementById('task-id').value;
    const title = (subtaskInput.value || '').trim();
    if (!taskId || !title) return;
    try {
      const data = await api('api/subtasks.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'create', task_id: Number(taskId), title }),
      });
      currentSubtasks.push(data.subtask);
      subtaskInput.value = '';
      renderSubtasks();
      dirtyAfterCreate = true;
    } catch (err) {
      alert(err.message);
    }
  }

  async function addComment() {
    const taskId = document.getElementById('task-id').value;
    const body = (commentInput.value || '').trim();
    if (!taskId || !body) return;
    try {
      const data = await api('api/comments.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'create', task_id: Number(taskId), body }),
      });
      currentComments.push(data.comment);
      commentInput.value = '';
      renderComments();
      dirtyAfterCreate = true;
    } catch (err) {
      alert(err.message);
    }
  }

  subtaskAddBtn.addEventListener('click', addSubtask);
  subtaskInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      addSubtask();
    }
  });
  commentAddBtn.addEventListener('click', addComment);

  subtaskList.addEventListener('click', async (e) => {
    const toggle = e.target.closest('[data-toggle-subtask]');
    const del = e.target.closest('[data-del-subtask]');
    try {
      if (toggle) {
        const id = Number(toggle.getAttribute('data-toggle-subtask'));
        const data = await api('api/subtasks.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'toggle', id }),
        });
        currentSubtasks = currentSubtasks.map((s) => (s.id === id ? data.subtask : s));
        renderSubtasks();
        dirtyAfterCreate = true;
      }
      if (del) {
        const id = Number(del.getAttribute('data-del-subtask'));
        await api('api/subtasks.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'delete', id }),
        });
        currentSubtasks = currentSubtasks.filter((s) => s.id !== id);
        renderSubtasks();
        dirtyAfterCreate = true;
      }
    } catch (err) {
      alert(err.message);
    }
  });

  commentList.addEventListener('click', async (e) => {
    const del = e.target.closest('[data-del-comment]');
    if (!del) return;
    const id = Number(del.getAttribute('data-del-comment'));
    try {
      await api('api/comments.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'delete', id }),
      });
      currentComments = currentComments.filter((c) => c.id !== id);
      renderComments();
      dirtyAfterCreate = true;
    } catch (err) {
      alert(err.message);
    }
  });

  document.querySelectorAll('[data-open-task]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const projectSelect = document.getElementById('task-project');
      const board = document.querySelector('.kanban');
      dirtyAfterCreate = false;
      fillForm({
        status: 'todo',
        priority: 'medium',
        project_id: board ? board.dataset.project : (projectSelect && projectSelect.value),
      });
      openModal();
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach((el) => {
    el.addEventListener('click', () => closeModal(false));
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) closeModal(false);
  });

  document.addEventListener('click', (e) => {
    if (suppressClick) {
      suppressClick = false;
      return;
    }
    const target = e.target.closest('[data-edit-task]');
    if (!target) return;
    if (target.classList.contains('kanban-card') && target.classList.contains('dragging')) return;
    const id = target.getAttribute('data-edit-task');
    if (id) {
      dirtyAfterCreate = false;
      editTask(id).catch((err) => alert(err.message));
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(form).entries());
    if (!payload.assignee_id) payload.assignee_id = null;
    if (!payload.due_date) payload.due_date = null;
    const isNew = !payload.id;
    if (!payload.id) delete payload.id;
    else payload.id = Number(payload.id);

    try {
      const data = await api('api/tasks.php', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      if (isNew && data.task && data.task.id) {
        dirtyAfterCreate = true;
        fillForm(Object.assign({}, data.task, { subtasks: [], comments: [] }));
        return;
      }
      location.reload();
    } catch (err) {
      alert(err.message);
    }
  });

  deleteBtn.addEventListener('click', async () => {
    const id = document.getElementById('task-id').value;
    if (!id || !confirm('Delete this task, subtasks, and comments?')) return;
    try {
      await api('api/tasks.php', {
        method: 'POST',
        body: JSON.stringify({ id: Number(id), action: 'delete' }),
      });
      location.reload();
    } catch (err) {
      alert(err.message);
    }
  });

  const board = document.querySelector('.kanban');
  if (!board) return;

  let dragged = null;

  board.querySelectorAll('.kanban-card').forEach((card) => {
    card.addEventListener('dragstart', (e) => {
      dragged = card;
      card.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.dataset.taskId);
    });
    card.addEventListener('dragend', () => {
      card.classList.remove('dragging');
      board.querySelectorAll('.drag-over').forEach((z) => z.classList.remove('drag-over'));
      suppressClick = true;
      setTimeout(() => { suppressClick = false; }, 50);
      dragged = null;
    });
  });

  board.querySelectorAll('[data-drop-zone]').forEach((zone) => {
    zone.addEventListener('dragover', (e) => {
      e.preventDefault();
      zone.classList.add('drag-over');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', async (e) => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      if (!dragged) return;
      const status = zone.dataset.dropZone;
      const id = Number(dragged.dataset.taskId);
      const overCard = e.target.closest('.kanban-card');
      if (overCard && overCard !== dragged && zone.contains(overCard)) {
        const rect = overCard.getBoundingClientRect();
        const before = e.clientY < rect.top + rect.height / 2;
        zone.insertBefore(dragged, before ? overCard : overCard.nextSibling);
      } else {
        zone.appendChild(dragged);
      }
      const orderedIds = [...zone.querySelectorAll('.kanban-card')].map((c) => Number(c.dataset.taskId));
      const position = orderedIds.indexOf(id);
      try {
        await api('api/tasks.php', {
          method: 'POST',
          body: JSON.stringify({ id, status, position, ordered_ids: orderedIds }),
        });
        board.querySelectorAll('.kanban-col').forEach((col) => {
          const count = col.querySelectorAll('.kanban-card').length;
          col.querySelector('.count').textContent = String(count);
        });
      } catch (err) {
        alert(err.message);
        location.reload();
      }
    });
  });
})();
