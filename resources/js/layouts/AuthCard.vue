<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from 'primevue/button';
import AuthIllustration from '../components/AuthIllustration.vue';

defineProps({
    /** Encabezado del panel del formulario. */
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    /** Texto grande del panel de marca. */
    heading: { type: String, default: '¡Bienvenido de nuevo!' },
    tagline: { type: String, default: 'Inicia sesión y continúa construyendo cosas increíbles.' },
});

const appName = import.meta.env.VITE_APP_NAME ?? 'PrimeVueKit';

// La ruta de demostración es opcional: `primevuekit:install --no-demo` no la crea.
const demoUrl = computed(() => (route().has('primevuekit.demo') ? route('primevuekit.demo') : null));
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-surface-100 p-4 dark:bg-surface-950">
        <div class="grid w-full max-w-4xl overflow-hidden rounded-3xl shadow-2xl lg:grid-cols-2">
            <section class="pvk-brand relative hidden flex-col justify-between gap-8 p-10 text-white lg:flex">
                <slot name="brand">
                    <div class="flex justify-center">
                        <AuthIllustration />
                    </div>

                    <div class="flex flex-col items-start gap-4">
                        <h2 class="text-3xl leading-tight font-bold">{{ heading }}</h2>
                        <p class="text-white/80">{{ tagline }}</p>

                        <Button
                            v-if="demoUrl"
                            :as="Link"
                            :href="demoUrl"
                            label="Ver demo"
                            icon="pi pi-arrow-right"
                            icon-pos="right"
                            rounded
                            variant="outlined"
                            class="border-white/70 text-white hover:bg-white/10"
                        />
                    </div>
                </slot>
            </section>

            <section class="flex flex-col gap-6 bg-surface-0 p-8 sm:p-10 dark:bg-surface-900">
                <slot name="logo">
                    <div class="flex items-center gap-2">
                        <svg class="size-7" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2 3 7v10l9 5 9-5V7z" fill="var(--p-primary-500)" />
                            <path d="M12 2v20l9-5V7z" fill="var(--p-primary-700)" />
                        </svg>
                        <span class="text-lg font-semibold text-color">{{ appName }}</span>
                    </div>
                </slot>

                <header class="flex flex-col gap-1">
                    <h1 class="text-2xl font-bold text-color">{{ title }}</h1>
                    <p v-if="subtitle" class="text-muted-color">{{ subtitle }}</p>
                </header>

                <slot />

                <footer v-if="$slots.footer" class="border-t border-surface pt-4 text-center text-sm">
                    <slot name="footer" />
                </footer>
            </section>
        </div>
    </div>
</template>

<style scoped>
/* Degradado a partir de los tokens del tema: sigue a la paleta sin repetir colores. */
.pvk-brand {
    background-image: linear-gradient(
        135deg,
        var(--p-primary-500) 0%,
        var(--p-primary-700) 45%,
        var(--p-primary-900) 100%
    );
}
</style>
