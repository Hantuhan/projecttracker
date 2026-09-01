<?php if (current_user()): ?>
  </main>
</div>
<?php else: ?>
</div>
<?php endif; ?>
<script>
  window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
  window.CURRENT_USER = <?= json_encode(current_user() ? [
      'id' => (int) current_user()['id'],
      'role' => current_user()['role'],
  ] : null) ?>;
</script>
<script src="assets/js/app.js"></script>
<?php if (!empty($pageScript)): ?>
<script src="assets/js/<?= e($pageScript) ?>"></script>
<?php endif; ?>
</body>
</html>
