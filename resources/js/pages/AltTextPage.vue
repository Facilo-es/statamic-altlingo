<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { Badge, Button, Card, Checkbox, Header, Input, Modal, Select } from '@statamic/cms/ui';
import { Head } from '@statamic/cms/inertia';
import { STATES, t, send } from '../states.js';

const props = defineProps({
    sites: Array,
    totals: Object,
    containers: Array,
    folders: Array,
    multisite: Boolean,
    can: Object,
    translator: Boolean,
    queue: Boolean,
    docsUrl: String,
    urls: Object,
    strings: Object,
    fieldStrings: Object,
});

const s = props.strings;
const f = props.fieldStrings;
const totals = ref(props.totals);
const main = computed(() => props.sites.find((x) => x.main));
const others = computed(() => props.sites.filter((x) => !x.main));
const siteName = (handle) => props.sites.find((x) => x.handle === handle)?.name ?? handle;

const filters = reactive({ container: '', folder: '', site: '', status: '', q: '' });
const rows = ref([]);
const meta = ref({ total: 0, page: 1, pages: 1 });
const loading = ref(false);
const selected = ref([]);
const editing = reactive({ id: null, site: null, text: '' });
const saving = ref(null);
const error = ref(null);

const statusLabel = (status) => ({ done: f.own, review: f.to_review, fallback: t(f.uses, { language: main.value?.name }), missing: f.missing, decorative: f.decorative })[status];
const statusOptions = ['missing', 'fallback', 'review', 'decorative', 'done'].map((v) => ({ value: v, label: s['status_' + v] }));

async function load(page = 1) {
    loading.value = true;
    const query = new URLSearchParams({ ...filters, page });
    try {
        const data = await send(props.urls.rows + '?' + query, {}, 'GET');
        rows.value = data.data;
        meta.value = data.meta;
        selected.value = [];
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

function filter(changes) {
    Object.assign(filters, changes);
    load(1);
}

const filtered = computed(() => Object.values(filters).some((v) => v !== ''));

// Cards: the segmented bar and counts per site (spec §6 coverage).
function segments(site) {
    const c = totals.value[site.handle];
    if (!c?.total) return [];
    return ['done', 'decorative', 'review', 'fallback', 'missing'].map((k) => ({ key: k, width: (c[k] * 100) / c.total })).filter((x) => x.width > 0);
}

// Editing: Enter saves and moves down, Tab saves and goes to the next language, Esc cancels.
function startEdit(row, site) {
    if (!props.can.edit || row.decorative || !site.editable) return;
    editing.id = row.id;
    editing.site = site.handle;
    editing.text = row.texts[site.handle] ?? '';
    nextTick(() => document.getElementById(cellId(row.id, site.handle))?.focus());
}

const cellId = (id, site) => 'altlingo-' + btoa(unescape(encodeURIComponent(id))).replace(/=/g, '') + '-' + site;

async function save(row) {
    const site = editing.site;
    const text = editing.text.trim();
    editing.id = null;
    if (text === (row.texts[site] ?? '')) return;
    saving.value = row.id + site;
    try {
        const data = await send(props.urls.cell, { id: row.id, site, text: text || null });
        Object.assign(row, data.row);
        totals.value = data.totals;
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = null;
    }
}

async function keydown(event, row, index) {
    if (event.key === 'Escape') {
        editing.id = null;
        return;
    }
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        const site = editing.site;
        await save(row);
        const next = rows.value.slice(index + 1).find((r) => !r.decorative);
        if (next) startEdit(next, props.sites.find((x) => x.handle === site));
    } else if (event.key === 'Tab') {
        event.preventDefault();
        const editable = props.sites.filter((x) => x.editable);
        const at = editable.findIndex((x) => x.handle === editing.site);
        const next = editable[(at + (event.shiftKey ? -1 + editable.length : 1)) % editable.length];
        await save(row);
        startEdit(row, next);
    }
}

// Selection and bulk actions.
const allSelected = computed(() => rows.value.length > 0 && selected.value.length === rows.value.length);
function toggleAll(on) {
    selected.value = on ? rows.value.map((r) => r.id) : [];
}
function toggle(id, on) {
    selected.value = on ? [...selected.value, id] : selected.value.filter((x) => x !== id);
}

const bulkSite = ref(others.value.find((x) => x.editable)?.handle ?? '');
const confirmClear = ref(false);

async function bulk(action) {
    confirmClear.value = false;
    try {
        const data = await send(props.urls.bulk, { action, ids: selected.value, site: bulkSite.value });
        totals.value = data.totals;
        Statamic.$toast.success(s.saved);
        load(meta.value.page);
    } catch (e) {
        Statamic.$toast.error(e.message);
    }
}

// Bulk translation: queued runs are polled, otherwise the page drives the batches itself.
const run = ref(null);
async function translate() {
    try {
        const start = await send(props.urls.translate, { ids: selected.value, sites: [bulkSite.value] });
        run.value = { total: start.total, done: 0 };
        let state;
        do {
            state = start.queued
                ? await new Promise((r) => setTimeout(r, 1500)).then(() => send(props.urls.progress.replace('__run__', start.run), {}, 'GET'))
                : await send(props.urls.batch, { run: start.run });
            run.value = state;
        } while (!state.finished);
        if (state.totals) totals.value = state.totals;
        const message = t(s.progress_done, { ok: state.ok, failed: state.failed });
        state.failed ? Statamic.$toast.error(message + ' ' + (state.errors[0] ?? '')) : Statamic.$toast.success(message);
        load(meta.value.page);
    } catch (e) {
        Statamic.$toast.error(e.message);
    } finally {
        run.value = null;
    }
}

// CSV import with a preview before anything is written.
const importing = reactive({ open: false, preview: null, review: true, busy: false, error: null });
async function upload(event) {
    const file = event.target.files[0];
    if (!file) return;
    importing.busy = true;
    importing.error = null;
    const body = new FormData();
    body.append('file', file);
    try {
        importing.preview = await send(props.urls.preview, body);
    } catch (e) {
        importing.error = e.message;
    } finally {
        importing.busy = false;
        event.target.value = '';
    }
}
async function apply() {
    importing.busy = true;
    try {
        const data = await send(props.urls.apply, { token: importing.preview.token, review: importing.review });
        totals.value = data.totals;
        closeImport();
        Statamic.$toast.success(s.saved);
        load(meta.value.page);
    } catch (e) {
        importing.error = e.message;
    } finally {
        importing.busy = false;
    }
}
function closeImport() {
    Object.assign(importing, { open: false, preview: null, error: null });
}
const exportUrl = computed(() => props.urls.export + '?' + new URLSearchParams(filters));

onMounted(() => load());
</script>

<template>
    <div class="altlingo-page">
        <Head :title="s.title" />
        <Header :title="s.title">
            <template v-if="multisite && containers.length">
                <Button :href="exportUrl" :text="s.export" />
                <Button v-if="can.edit" :text="s.import" @click="importing.open = true" />
            </template>
        </Header>

        <Card v-if="!multisite" class="p-6 text-sm">{{ s.single_site }}</Card>
        <Card v-else-if="!containers.length || !totals[main.handle]?.total" class="p-6 text-sm">{{ s.empty }}</Card>

        <template v-else>
            <p class="-mt-2 mb-4 text-sm text-gray-600 dark:text-gray-400">{{ s.subtitle }}</p>

            <div class="mb-6 grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(230px, 1fr))">
                <Card v-for="site in sites" :key="site.handle" class="p-4">
                    <div class="mb-1 flex items-center gap-2 text-sm font-semibold">
                        {{ site.name }}
                        <Badge v-if="site.main" size="sm" :text="f.main" />
                    </div>
                    <div class="mb-2 text-3xl font-semibold tabular-nums">{{ totals[site.handle].percent }}%<span class="ms-1.5 text-base font-medium text-gray-500">{{ s.done_word }}</span></div>
                    <div class="mb-3 flex h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <span v-for="seg in segments(site)" :key="seg.key" class="h-full" :class="STATES[seg.key].dot" :style="{ width: seg.width + '%' }" />
                    </div>
                    <p v-if="totals[site.handle].missing + totals[site.handle].fallback + totals[site.handle].review === 0" class="text-xs text-gray-600 dark:text-gray-400">
                        {{ t(s.all_done, { language: site.name }) }}
                    </p>
                    <ul v-else class="space-y-1 text-xs">
                        <template v-for="key in ['missing', 'fallback', 'review']" :key="key">
                            <li v-if="totals[site.handle][key]">
                                <button type="button" class="flex items-center gap-1.5 text-gray-700 hover:underline dark:text-gray-300" @click="filter({ site: site.handle, status: key })">
                                    <span class="size-2 rounded-full" :class="STATES[key].dot" />
                                    <span class="font-semibold tabular-nums">{{ totals[site.handle][key] }}</span>
                                    {{ key === 'fallback' ? statusLabel(key) : s['status_' + key] }}
                                </button>
                            </li>
                        </template>
                    </ul>
                </Card>
            </div>

            <div v-if="can.translate && !translator" class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                {{ s.no_key }}
                <a :href="docsUrl" target="_blank" rel="noopener" class="font-medium underline">{{ s.no_key_link }}</a>
            </div>

            <div class="mb-3 flex flex-wrap items-center gap-2">
                <Select v-if="containers.length > 1" class="w-44" :model-value="filters.container || null" :options="containers" clearable :placeholder="s.filter_container" @update:model-value="filter({ container: $event ?? '' })" />
                <Select v-if="folders.length > 1" class="w-44" :model-value="filters.folder || null" :options="folders" clearable :placeholder="s.filter_folder" @update:model-value="filter({ folder: $event ?? '' })" />
                <Select class="w-44" :model-value="filters.site || null" :options="sites.map((x) => ({ value: x.handle, label: x.name }))" clearable :placeholder="s.filter_language" @update:model-value="filter({ site: $event ?? '' })" />
                <Select class="w-52" :model-value="filters.status || null" :options="statusOptions" clearable :placeholder="s.filter_status" @update:model-value="filter({ status: $event ?? '' })" />
                <Input class="w-64" icon="magnifying-glass" :model-value="filters.q" :placeholder="s.search" clearable @update:model-value="filter({ q: $event ?? '' })" />
                <Button v-if="filtered" variant="ghost" size="sm" :text="s.clear_filters" @click="filter({ container: '', folder: '', site: '', status: '', q: '' })" />
                <span class="flex-1" />
                <span class="text-sm text-gray-600 tabular-nums dark:text-gray-400">{{ t(s.images_count, { n: meta.total }) }}</span>
            </div>

            <div v-if="selected.length && can.edit" class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-850">
                <span class="font-medium">{{ t(s.selected, { n: selected.length }) }}</span>
                <span class="mx-1 h-5 w-px bg-gray-300 dark:bg-gray-700" />
                <Select class="w-40" size="sm" :model-value="bulkSite" :options="others.filter((x) => x.editable).map((x) => ({ value: x.handle, label: x.name }))" @update:model-value="bulkSite = $event" />
                <Button v-if="can.translate" size="sm" variant="primary" :disabled="!translator || !!run" :text="s.bulk_translate.replace('…', ' ' + siteName(bulkSite))" @click="translate" />
                <Button size="sm" :text="s.bulk_reviewed" @click="bulk('reviewed')" />
                <Button size="sm" :text="t(s.bulk_clear, { language: siteName(bulkSite) })" @click="confirmClear = true" />
                <Button v-if="main.editable" size="sm" :text="s.bulk_decorative" @click="bulk('decorative')" />
            </div>

            <div v-if="run" class="mb-3">
                <div class="mb-1 text-sm">{{ t(s.progress, { done: run.done, total: run.total }) }}</div>
                <div class="h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <span class="block h-full bg-[var(--color-primary)] transition-[width] duration-500" :style="{ width: (run.total ? (run.done * 100) / run.total : 0) + '%' }" />
                </div>
            </div>

            <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

            <Card class="overflow-x-auto p-0">
                <table class="w-full min-w-[900px] text-sm" :class="{ 'opacity-60': loading }">
                    <thead>
                        <tr class="border-b border-gray-200 text-start text-xs text-gray-600 dark:border-gray-700 dark:text-gray-400">
                            <th class="w-10 px-3 py-2.5"><Checkbox v-if="can.edit" solo size="sm" :model-value="allSelected" @update:model-value="toggleAll" /></th>
                            <th class="w-56 px-3 py-2.5 text-start font-medium">{{ s.image }}</th>
                            <th v-for="site in sites" :key="site.handle" class="px-3 py-2.5 text-start font-medium" style="min-width: 220px">
                                {{ site.name }} <span v-if="site.main" class="font-normal">· {{ f.main.toLowerCase() }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td :colspan="sites.length + 2" class="px-3 py-8 text-center text-gray-600 dark:text-gray-400">{{ s.no_results }}</td>
                        </tr>
                        <tr v-for="(row, index) in rows" :key="row.id" class="border-b border-gray-100 align-top last:border-0 dark:border-gray-800">
                            <td class="px-3 py-3"><Checkbox v-if="can.edit" solo size="sm" :model-value="selected.includes(row.id)" @update:model-value="toggle(row.id, $event)" /></td>
                            <td class="px-3 py-3">
                                <a :href="row.edit_url" class="flex items-center gap-3">
                                    <img :src="row.thumbnail" alt="" class="size-12 shrink-0 rounded object-cover" loading="lazy" />
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium">{{ row.file }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ row.folder }}</span>
                                    </span>
                                </a>
                            </td>
                            <td v-if="row.decorative" :colspan="sites.length" class="px-3 py-3">
                                <div class="flex items-center gap-2 rounded-md border-s-4 border-violet-600 bg-violet-50 px-3 py-2 text-violet-900 dark:bg-violet-950 dark:text-violet-200">
                                    <Badge color="violet" size="sm" :text="f.decorative" />
                                    {{ s.decorative_row }}
                                </div>
                            </td>
                            <template v-else>
                                <td
                                    v-for="site in sites"
                                    :key="site.handle"
                                    class="cursor-text px-3 py-3"
                                    :class="{
                                        'bg-red-50 dark:bg-red-950/40': row.status[site.handle] === 'missing',
                                        'bg-amber-50 dark:bg-amber-950/40': row.status[site.handle] === 'review',
                                    }"
                                    @click="editing.id === row.id && editing.site === site.handle ? null : startEdit(row, site)"
                                >
                                    <textarea
                                        v-if="editing.id === row.id && editing.site === site.handle"
                                        :id="cellId(row.id, site.handle)"
                                        v-model="editing.text"
                                        rows="2"
                                        maxlength="1000"
                                        class="w-full resize-none rounded border border-gray-300 bg-white px-2 py-1 text-sm focus:outline-2 focus:outline-[var(--color-primary)] dark:border-gray-700 dark:bg-gray-900"
                                        :placeholder="row.hints[site.handle] ? t(f.placeholder, { language: site.fallbackName ?? main.name, text: row.hints[site.handle] }) : f.help"
                                        @keydown="keydown($event, row, index)"
                                        @blur="editing.id === row.id && save(row)"
                                    />
                                    <div v-else-if="row.texts[site.handle]" class="line-clamp-3">{{ row.texts[site.handle] }}</div>
                                    <div v-else-if="row.hints[site.handle]" class="line-clamp-2 text-gray-500 dark:text-gray-400">
                                        {{ t(f.placeholder, { language: site.fallbackName ?? main.name, text: row.hints[site.handle] }) }}
                                    </div>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <Badge :color="STATES[row.status[site.handle]].badge" size="sm" :text="statusLabel(row.status[site.handle])" />
                                        <span v-if="saving === row.id + site.handle" class="text-xs text-gray-500">…</span>
                                    </div>
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-600 dark:text-gray-400">
                <span>{{ s.edit_hint }}</span>
                <span class="flex-1" />
                <template v-if="meta.pages > 1">
                    <Button size="sm" variant="ghost" icon="chevron-left" :disabled="meta.page <= 1" @click="load(meta.page - 1)" />
                    <span class="tabular-nums">{{ t(s.page_of, { page: meta.page, pages: meta.pages }) }}</span>
                    <Button size="sm" variant="ghost" icon="chevron-right" :disabled="meta.page >= meta.pages" @click="load(meta.page + 1)" />
                </template>
            </div>
        </template>

        <Modal :title="t(s.bulk_clear, { language: siteName(bulkSite) })" :open="confirmClear" @update:open="confirmClear = $event">
            <p class="text-sm">{{ t(s.clear_confirm, { language: siteName(bulkSite), n: selected.length, fallback: sites.find((x) => x.handle === bulkSite)?.fallbackName ?? main.name }) }}</p>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button :text="s.cancel" variant="ghost" @click="confirmClear = false" />
                    <Button :text="t(s.bulk_clear, { language: siteName(bulkSite) })" variant="danger" @click="bulk('clear')" />
                </div>
            </template>
        </Modal>

        <Modal :title="s.import" :open="importing.open" @update:open="$event ? (importing.open = true) : closeImport()">
            <div class="space-y-4 text-sm">
                <input v-if="!importing.preview" type="file" accept=".csv,text/csv" :disabled="importing.busy" @change="upload" />
                <template v-if="importing.preview">
                    <p class="text-base font-medium">{{ t(s.preview, importing.preview) }}</p>
                    <ul class="max-h-72 space-y-2 overflow-y-auto">
                        <li v-for="change in importing.preview.changes" :key="change.id + change.site" class="flex items-start gap-3">
                            <img :src="change.thumbnail" alt="" class="size-10 shrink-0 rounded object-cover" />
                            <div class="min-w-0">
                                <div class="text-xs text-gray-500">{{ change.file }} · {{ siteName(change.site) }}</div>
                                <div v-if="change.before" class="text-gray-500 line-through">{{ change.before }}</div>
                                <div class="rounded bg-green-50 px-1 text-green-900 dark:bg-green-950 dark:text-green-200">{{ change.after }}</div>
                            </div>
                        </li>
                    </ul>
                    <ul v-if="importing.preview.errors.length" class="space-y-0.5 text-xs text-red-600">
                        <li v-for="line in importing.preview.errors.slice(0, 20)" :key="line">{{ line }}</li>
                    </ul>
                    <Checkbox v-model="importing.review" :label="s.mark_imported" />
                </template>
                <p v-if="importing.error" class="text-red-600">{{ importing.error }}</p>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button :text="s.cancel" variant="ghost" @click="closeImport" />
                    <Button v-if="importing.preview" :text="s.apply" variant="primary" :loading="importing.busy" :disabled="!importing.preview.changes.length" @click="apply" />
                </div>
            </template>
        </Modal>
    </div>
</template>
