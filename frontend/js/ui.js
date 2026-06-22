function showToast(msg, type = '') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'show' + (type ? ` ${type}` : '');
  clearTimeout(el._t);
  el._t = setTimeout(() => { el.className = ''; }, 2800);
}

function requireAuth() {
  if (!getToken()) { location.href = '/'; }
}

function openSheet(id) {
  document.getElementById(id).classList.add('open');
  document.getElementById(id).querySelector('input, textarea')?.focus();
}

function closeSheet(id) {
  document.getElementById(id).classList.remove('open');
}

function setLoading(btn, loading) {
  if (loading) {
    btn.dataset.label = btn.textContent;
    btn.innerHTML = '<span class="spinner"></span>';
    btn.disabled = true;
  } else {
    btn.textContent = btn.dataset.label || btn.textContent;
    btn.disabled = false;
  }
}
