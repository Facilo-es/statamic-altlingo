// One colour and one word for every state, never colour alone.
export const STATES = {
    done: { badge: 'green', dot: 'bg-green-600' },
    review: { badge: 'amber', dot: 'bg-amber-500' },
    fallback: { badge: 'default', dot: 'bg-gray-400' },
    missing: { badge: 'red', dot: 'bg-red-500' },
    decorative: { badge: 'violet', dot: 'bg-violet-600' },
};

export function t(text, replace = {}) {
    return Object.entries(replace).reduce((s, [k, v]) => s.replaceAll(':' + k, v ?? ''), text ?? '');
}

export async function send(url, body = {}, method = 'POST') {
    const token = Statamic.$config.get('csrfToken') || document.querySelector('meta[name="csrf-token"]')?.content;
    const options = { method, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } };
    if (body instanceof FormData) {
        options.body = body;
    } else if (method !== 'GET') {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }
    const response = await fetch(url, options);
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || response.statusText);
    return data;
}
