<script setup>
import { Widget } from '@statamic/cms/ui';

defineProps({ title: String, sites: Array, url: String, linkText: String });
</script>

<template>
    <Widget :title="title" icon="translate">
        <ul class="space-y-3 px-4 py-3">
            <li v-for="site in sites" :key="site.handle">
                <div class="mb-1 flex justify-between text-sm">
                    <span class="font-medium">{{ site.name }}</span>
                    <span class="text-gray-600 tabular-nums dark:text-gray-400">{{ site.percent }}%</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <span class="block h-full rounded-full" :class="site.percent === 100 ? 'bg-green-600' : 'bg-[var(--color-primary)]'" :style="{ width: site.percent + '%' }" />
                </div>
            </li>
        </ul>
        <template #actions>
            <a :href="url" class="text-sm text-[var(--color-primary)] hover:underline">{{ linkText }}</a>
        </template>
    </Widget>
</template>
