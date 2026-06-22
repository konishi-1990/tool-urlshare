// API ベース URL — 本番デプロイ時はここを変更する
const API_BASE = 'http://localhost/api/v1';

// ── ストレージ ──
function getToken() {
  return new Promise(resolve => chrome.storage.local.get('token', r => resolve(r.token || null)));
}
function setToken(t) {
  return new Promise(resolve => chrome.storage.local.set({ token: t }, resolve));
}
function clearToken() {
  return new Promise(resolve => chrome.storage.local.remove('token', resolve));
}

// ── API ──
async function apiRequest(method, path, body, token) {
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const res = await fetch(API_BASE + path, {
    method, headers,
    body: body ? JSON.stringify(body) : undefined,
  });
  return res;
}

// ── ビュー切り替え ──
function showView(id) {
  ['view-login', 'view-save', 'view-done'].forEach(v => {
    document.getElementById(v).style.display = v === id ? '' : 'none';
  });
}

// ── setLoading ──
function setLoading(btn, loading, label) {
  if (loading) {
    btn.dataset.label = btn.textContent;
    btn.innerHTML = '<span class="spinner"></span>';
    btn.disabled = true;
  } else {
    btn.textContent = btn.dataset.label || label || btn.textContent;
    btn.disabled = false;
  }
}

// ── ステータス選択 ──
let selectedStatus = 'temporary';

document.getElementById('btn-temporary').addEventListener('click', () => {
  selectedStatus = 'temporary';
  document.getElementById('btn-temporary').className = 'status-btn selected-temporary';
  document.getElementById('btn-bookmarked').className = 'status-btn';
});
document.getElementById('btn-bookmarked').addEventListener('click', () => {
  selectedStatus = 'bookmarked';
  document.getElementById('btn-temporary').className = 'status-btn';
  document.getElementById('btn-bookmarked').className = 'status-btn selected-bookmarked';
});

// ── ログイン ──
document.getElementById('login-btn').addEventListener('click', async () => {
  const btn      = document.getElementById('login-btn');
  const errEl    = document.getElementById('login-error');
  const email    = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-password').value;

  errEl.classList.remove('visible');

  if (!email || !password) {
    errEl.textContent = 'メールアドレスとパスワードを入力してください';
    errEl.classList.add('visible');
    return;
  }

  setLoading(btn, true);
  try {
    const res = await apiRequest('POST', '/auth/login', { email, password });
    if (res.ok) {
      const data = await res.json();
      await setToken(data.token);
      await initSaveView();
    } else if (res.status === 401) {
      errEl.textContent = 'メールアドレスまたはパスワードが正しくありません';
      errEl.classList.add('visible');
    } else {
      errEl.textContent = 'ログインに失敗しました';
      errEl.classList.add('visible');
    }
  } catch {
    errEl.textContent = 'ネットワークエラーが発生しました';
    errEl.classList.add('visible');
  } finally {
    setLoading(btn, false, 'ログイン');
  }
});

// ── ログアウト ──
async function handleLogout() {
  const token = await getToken();
  if (token) {
    try { await apiRequest('POST', '/auth/logout', null, token); } catch {}
  }
  await clearToken();
  showView('view-login');
}
document.getElementById('logout-btn').addEventListener('click', handleLogout);
document.getElementById('logout-btn-done').addEventListener('click', handleLogout);

// ── 保存ビュー初期化 ──
async function initSaveView() {
  showView('view-save');
  document.getElementById('preview-title').textContent = '読み込み中…';
  document.getElementById('preview-url').textContent   = '';
  document.getElementById('save-error').classList.remove('visible');

  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    document.getElementById('preview-url').textContent   = tab.url || '';
    document.getElementById('preview-title').textContent = tab.title || tab.url || '';
  } catch {
    document.getElementById('preview-title').textContent = '(取得できませんでした)';
  }
}

// ── 保存 ──
document.getElementById('save-btn').addEventListener('click', async () => {
  const btn    = document.getElementById('save-btn');
  const errEl  = document.getElementById('save-error');
  errEl.classList.remove('visible');

  const token = await getToken();
  if (!token) { showView('view-login'); return; }

  let url;
  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    url = tab.url;
  } catch {
    errEl.textContent = 'タブ情報を取得できませんでした';
    errEl.classList.add('visible');
    return;
  }

  if (!url || (!url.startsWith('http://') && !url.startsWith('https://'))) {
    errEl.textContent = 'この URL は保存できません';
    errEl.classList.add('visible');
    return;
  }

  setLoading(btn, true);
  try {
    const res = await apiRequest('POST', '/urls', { url, status: selectedStatus }, token);

    if (res.ok) {
      const label = selectedStatus === 'bookmarked' ? '★ ブックマークに保存しました' : '⏰ あとで読むに保存しました';
      document.getElementById('done-text').textContent = label;
      document.getElementById('done-sub').textContent = url.length > 50 ? url.slice(0, 50) + '…' : url;
      showView('view-done');
    } else if (res.status === 401) {
      await clearToken();
      showView('view-login');
    } else if (res.status === 409) {
      errEl.textContent = 'この URL はすでに登録されています';
      errEl.classList.add('visible');
    } else if (res.status === 422) {
      const data = await res.json();
      errEl.textContent = data.errors?.url?.[0] ?? '入力値が不正です';
      errEl.classList.add('visible');
    } else {
      errEl.textContent = '保存に失敗しました';
      errEl.classList.add('visible');
    }
  } catch {
    errEl.textContent = 'ネットワークエラーが発生しました';
    errEl.classList.add('visible');
  } finally {
    setLoading(btn, false, '保存する');
  }
});

// ── 「別の URL を保存」 ──
document.getElementById('save-another-btn').addEventListener('click', async () => {
  await initSaveView();
});

// ── 起動時 ──
(async () => {
  const token = await getToken();
  if (!token) {
    showView('view-login');
  } else {
    await initSaveView();
  }
})();
