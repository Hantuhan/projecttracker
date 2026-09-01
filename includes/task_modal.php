<?php

declare(strict_types=1);

$projects = $projects ?? accessible_projects($pdo, $user);
$members = $members ?? active_users($pdo);
?>
<div class="modal" id="task-modal" hidden>
  <div class="modal-backdrop" data-close-modal></div>
  <div class="modal-dialog modal-wide" role="dialog" aria-modal="true" aria-labelledby="task-modal-title">
    <div class="modal-head">
      <h2 id="task-modal-title">Task</h2>
      <button type="button" class="icon-btn" data-close-modal aria-label="Close">&times;</button>
    </div>
    <form id="task-form" class="stack">
      <input type="hidden" name="id" id="task-id">
      <label>Title <input name="title" id="task-title" required maxlength="255"></label>
      <label>Description <textarea name="description" id="task-description" rows="3"></textarea></label>
      <div class="form-grid">
        <label>Project
          <select name="project_id" id="task-project" required>
            <?php foreach ($projects as $p): ?>
              <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Status
          <select name="status" id="task-status">
            <option value="todo">To Do</option>
            <option value="in_progress">In Progress</option>
            <option value="review">Review</option>
            <option value="done">Done</option>
          </select>
        </label>
        <label>Priority
          <select name="priority" id="task-priority">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
          </select>
        </label>
        <label>Assignee
          <select name="assignee_id" id="task-assignee">
            <option value="">Unassigned</option>
            <?php foreach ($members as $m): ?>
              <option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Due date <input type="date" name="due_date" id="task-due"></label>
      </div>

      <div class="subtasks-block" id="subtasks-block" hidden>
        <div class="panel-head">
          <h2>Subtasks <span class="subtask-progress" id="subtask-progress"></span></h2>
        </div>
        <ul class="subtask-list" id="subtask-list"></ul>
        <div class="subtask-add">
          <input type="text" id="subtask-input" placeholder="Add a subtask…" maxlength="255">
          <button type="button" class="btn" id="subtask-add-btn">Add</button>
        </div>
        <p class="muted tiny" id="subtask-hint" hidden>Save the task first, then add subtasks.</p>
      </div>

      <div class="comments-block" id="comments-block" hidden>
        <div class="panel-head">
          <h2>Comments</h2>
        </div>
        <ul class="comment-list" id="comment-list"></ul>
        <div class="comment-add">
          <textarea id="comment-input" rows="2" placeholder="Write a comment…"></textarea>
          <button type="button" class="btn" id="comment-add-btn">Post</button>
        </div>
        <p class="muted tiny" id="comment-hint" hidden>Save the task first to comment.</p>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-danger" id="task-delete" hidden>Delete</button>
        <div class="spacer"></div>
        <button type="button" class="btn" data-close-modal id="task-done-btn">Close</button>
        <button type="submit" class="btn btn-primary" id="task-save-btn">Save</button>
      </div>
    </form>
  </div>
</div>
