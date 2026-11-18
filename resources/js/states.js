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
