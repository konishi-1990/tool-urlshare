const BASE = '/api/v1';

function getToken() { return localStorage.getItem('token'); }
function setToken(t) { localStorage.setItem('token', t); }
function clearToken() { localStorage.removeItem('token'); }

async function request(method, path, body) {
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
  const token = getToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const res = await fetch(BASE + path, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  if (res.status === 401 && getToken()) {
    clearToken();
    location.href = '/';
    return;
  }

  return res;
}

const api = {
  async register(email, password) {
    return request('POST', '/auth/register', { email, password });
  },
  async login(email, password) {
    const res = await request('POST', '/auth/login', { email, password });
    if (res.ok) {
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
  async listUrls(status) {
    const q = status ? `?status=${status}` : '';
    return request('GET', `/urls${q}`);
  },
  async saveUrl(url, status = 'temporary') {
    return request('POST', '/urls', { url, status });
  },
  async updateStatus(id, status) {
    return request('PATCH', `/urls/${id}`, { status });
  },
  async deleteUrl(id) {
    return request('DELETE', `/urls/${id}`);
  },
};
