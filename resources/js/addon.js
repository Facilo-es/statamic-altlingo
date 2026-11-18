import AltLingoFieldtype from './components/AltLingoFieldtype.vue';

Statamic.booting(() => {
    Statamic.component('altlingo-fieldtype', AltLingoFieldtype);
});
