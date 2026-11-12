<script setup>
import { computed, reactive, ref } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Badge, Button, Switch, Textarea, Select } from '@statamic/cms/ui';
import { STATES, t, send } from '../states.js';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update, isReadOnly } = Fieldtype.use(emit, props);
defineExpose(expose);

const meta = props.meta;
const s = meta.strings;
const multisite = meta.sites.length > 1;
const main = meta.sites.find((site) => site.main) ?? meta.sites[0];

const state = reactive({
    main: typeof props.value === 'string' ? props.value : (meta.record.main ?? ''),
    sites: { ...(meta.record.sites || {}) },
    decorative: !!meta.record.decorative,
    review: [...(meta.record.review || [])],
});
const current = ref(main.handle);
const busy = ref(false);
const error = ref(null);

const site = computed(() => meta.sites.find((x) => x.handle === current.value));
const text = computed(() => (site.value.main ? state.main : (state.sites[current.value] ?? '')));
const placeholder = computed(() => (site.value.main || !state.main ? '' : t(s.placeholder, { language: main.name, text: state.main })));
const locked = computed(() => isReadOnly.value || !site.value.editable);

function status(handle) {
    if (meta.isAltField && state.decorative) return 'decorative';
    if (handle === main.handle) return state.main.trim() ? 'done' : 'missing';
    if ((state.sites[handle] ?? '').trim()) return state.review.includes(handle) ? 'review' : 'done';
    return meta.fallbacks[handle] || state.main.trim() ? 'fallback' : 'missing';
}

function label(handle) {
    const st = status(handle);
    if (st === 'done') return handle === main.handle ? s.main : s.own;
    if (st === 'fallback') {
        const from = meta.sites.find((x) => x.handle === (meta.fallbacks[handle] || main.handle));
        return t(s.uses, { language: from?.name ?? main.name });
    }
    return st === 'review' ? s.to_review : st === 'missing' ? s.missing : s.decorative;
}

function emitValue() {
    update({ main: state.main, sites: { ...state.sites }, decorative: state.decorative, review: [...state.review] });
}

function write(value) {
    if (site.value.main) {
        state.main = value;
    } else {
        state.sites[current.value] = value;
        state.review = state.review.filter((h) => h !== current.value);
    }
    multisite ? emitValue() : update(value);
}

function markReviewed() {
    state.review = state.review.filter((h) => h !== current.value);
    emitValue();
}

function toggleDecorative(on) {
    state.decorative = on;
    emitValue();
}

async function translate() {
    busy.value = true;
    error.value = null;
    try {
        const data = await send(meta.translateUrl, { site: current.value, field: props.handle });
        state.sites[current.value] = data.text;
        if (!state.review.includes(current.value)) state.review.push(current.value);
        emitValue();
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div v-if="!multisite">
        <Textarea :model-value="state.main" :read-only="isReadOnly" :rows="3" elastic @update:model-value="write" />
    </div>
    <div v-else class="altlingo-field">
        <div v-if="meta.sites.length <= 4" role="tablist" class="mb-2 flex flex-wrap gap-x-1 border-b border-gray-200 dark:border-gray-700">
            <button
                v-for="item in meta.sites"
                :key="item.handle"
                type="button"
                role="tab"
                :aria-selected="current === item.handle"
                class="-mb-px flex flex-col items-start gap-0.5 border-b-2 px-2.5 pt-1.5 pb-2 text-start whitespace-nowrap"
                :class="current === item.handle ? 'border-[var(--color-primary)] text-gray-900 dark:text-white' : 'border-transparent text-gray-600 dark:text-gray-400'"
                @click="current = item.handle"
            >
                <span class="text-sm font-semibold">{{ item.name }}</span>
                <span class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                    <span class="size-1.5 rounded-full" :class="STATES[status(item.handle)].dot" />
                    {{ label(item.handle) }}
                </span>
            </button>
        </div>
        <div v-else class="mb-2 flex items-center gap-2">
            <Select
                class="w-64"
                :model-value="current"
                :options="meta.sites.map((x) => ({ value: x.handle, label: `${x.name} · ${label(x.handle)}` }))"
                @update:model-value="current = $event"
            />
            <Badge :color="STATES[status(current)].badge" :text="label(current)" />
        </div>

        <template v-if="!(meta.isAltField && state.decorative)">
            <div v-if="status(current) === 'review'" class="mb-2 flex items-center gap-2 text-sm">
                <Badge color="amber" :text="s.to_review" v-tooltip="s.to_review_tip" />
                <span class="flex-1" />
                <Button size="sm" variant="ghost" :text="s.mark_reviewed" @click="markReviewed" />
            </div>
            <Textarea
                :key="current"
                :model-value="text"
                :placeholder="placeholder"
                :read-only="locked"
                :rows="3"
                elastic
                :dir="site.lang && ['ar', 'he', 'fa'].includes(site.lang) ? 'rtl' : 'ltr'"
                @update:model-value="write"
            />
            <p v-if="!site.editable" class="mt-1 text-xs text-gray-500">{{ s.no_access }}</p>
            <p v-if="text.length > 125" class="mt-1.5 text-xs text-amber-700 dark:text-amber-400">{{ s.long_hint }}</p>
            <p v-if="error" class="mt-1.5 text-xs text-red-600">{{ error }}</p>
            <div class="mt-2 flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                <Button
                    v-if="!site.main && meta.canTranslate && meta.translateUrl && !locked"
                    size="sm"
                    :loading="busy"
                    :text="busy ? s.translating : t(s.translate, { language: main.name })"
                    @click="translate"
                />
                <span class="flex-1" />
                <span>{{ t(s.characters, { n: text.length }) }}</span>
            </div>
        </template>

        <div v-if="meta.isAltField" class="mt-3.5 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <Switch :model-value="state.decorative" :disabled="isReadOnly || !main.editable" size="sm" @update:model-value="toggleDecorative" />
            {{ s.decorative_switch }}
        </div>
    </div>
</template>
