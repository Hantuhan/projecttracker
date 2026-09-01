async function api(url, options = {}) {
  const headers = Object.assign(
    { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
    options.headers || {}
  );
  const res = await fetch(url, Object.assign({}, options, { headers }));
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(data.error || 'Request failed');
  }
  return data;
}

document.addEventListener('click', (e) => {
  const row = e.target.closest('[data-edit-task]');
  if (row && !e.target.closest('.kanban-card')) {
    // kanban cards handle their own click vs drag
  }
});
