import AltLingoFieldtype from './components/AltLingoFieldtype.vue';
import CoverageWidget from './components/CoverageWidget.vue';
import { inertia } from '@statamic/cms/api';
import AltTextPage from './pages/AltTextPage.vue';

Statamic.booting(() => {
    Statamic.component('altlingo-fieldtype', AltLingoFieldtype);
    Statamic.component('altlingo-coverage-widget', CoverageWidget);
    inertia.register('altlingo::AltText', AltTextPage);
});
