const BASE = '/api/v1';

function getToken()  { return localStorage.getItem('admin_token'); }
function setToken(t) { localStorage.setItem('admin_token', t); }
function clearToken(){ localStorage.removeItem('admin_token'); }

async function request(method, path, body) {
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
  const token = getToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const res = await fetch(BASE + path, {
    method, headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  if (res.status === 401 && getToken()) {
    clearToken();
    location.href = '/admin/';
    return;
  }

  return res;
}

const api = {
  async login(email, password) {
    const res = await request('POST', '/auth/login', { email, password });
    if (res && res.ok) {
      const data = await res.json();
      setToken(data.token);
    }
    return res;
  },
  async logout() {
    const res = await request('POST', '/auth/logout');
    clearToken();
    return res;
  },
  async listUrls(params = {}) {
    const q = new URLSearchParams();
    if (params.status) q.set('status', params.status);
    if (params.search) q.set('search', params.search);
    const qs = q.toString() ? `?${q}` : '';
    return request('GET', `/admin/urls${qs}`);
  },
  async deleteUrl(id) {
    return request('DELETE', `/admin/urls/${id}`);
  },
  async listUsers() {
    return request('GET', '/admin/users');
  },
  async createUser(email, password, is_admin) {
    return request('POST', '/admin/users', { email, password, is_admin });
  },
  async deleteUser(id) {
    return request('DELETE', `/admin/users/${id}`);
  },
  async exportBookmarks() {
    const token = getToken();
    const res = await fetch(BASE + '/admin/export/bookmarks', {
      headers: { 'Accept': 'text/html', 'Authorization': `Bearer ${token}` },
    });
    if (res.status === 401) { clearToken(); location.href = '/admin/'; return null; }
    return res;
  },
};
