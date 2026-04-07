import '../css/app.css';
import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import i18n, { setLocale } from './i18n/index.js';

const appName = import.meta.env.VITE_APP_NAME || 'FinVero NBFS';

// Apply saved locale on initial load (sets dir + lang on <html>)
const savedLocale = localStorage.getItem('fv_locale') || 'en';
setLocale(savedLocale);

createInertiaApp({
    title: (title) => `${title} — ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)          // ← Register vue-i18n
            .mount(el);
    },
    progress: {
        color: '#3B82F6',
    },
});