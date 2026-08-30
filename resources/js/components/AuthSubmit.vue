<script setup>
import Button from 'primevue/button';

/**
 * Botón principal de los formularios de autenticación.
 *
 * Centraliza el degradado para no repetir la cadena de clases en las nueve páginas. Las
 * utilidades de Tailwind ganan a los estilos de PrimeVue porque la capa `primevue` va
 * antes de `utilities` (ver la opción cssLayer en app.js).
 */
defineProps({
    label: { type: String, required: true },
    loading: { type: Boolean, default: false },
    icon: { type: String, default: 'pi pi-arrow-right' },
});
</script>

<template>
    <Button
        type="submit"
        :label="label"
        :icon="icon"
        icon-pos="right"
        :loading="loading"
        fluid
        class="border-0 bg-linear-to-r from-primary-600 to-primary-800 hover:from-primary-700 hover:to-primary-900"
    />
</template>
