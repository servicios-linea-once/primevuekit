<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    security: {
        type: Object,
        default: () => ({ emailVerified: true, totpEnabled: false, emailOtpEnabled: false, canManage: true }),
    },
});

const factors = [
    { key: 'emailVerified', label: 'Correo verificado' },
    { key: 'totpEnabled', label: 'Segundo factor de aplicación (TOTP)' },
    { key: 'emailOtpEnabled', label: 'Código por correo (OTP)' },
];
</script>

<template>
    <Head title="Panel" />

    <AppLayout title="Panel">
        <Card>
            <template #title>Estado de la cuenta</template>
            <template #content>
                <ul class="flex flex-col gap-3">
                    <li v-for="factor in factors" :key="factor.key" class="flex items-center justify-between gap-4">
                        <span>{{ factor.label }}</span>
                        <Tag
                            :value="props.security[factor.key] ? 'Activo' : 'Inactivo'"
                            :severity="props.security[factor.key] ? 'success' : 'warn'"
                        />
                    </li>
                </ul>
            </template>
            <template v-if="props.security.canManage" #footer>
                <Link :href="route('two-factor.show')" class="text-primary hover:underline">
                    Gestionar la verificación en dos pasos
                </Link>
            </template>
        </Card>

        <Card>
            <template #title>Empieza por aquí</template>
            <template #content>
                <p class="text-muted-color">
                    Esta página es un punto de partida: está en
                    <code>resources/js/Pages/Dashboard.vue</code> y su controlador en
                    <code>app/Http/Controllers/DashboardController.php</code>. Edítalos a tu gusto.
                </p>
            </template>
        </Card>
    </AppLayout>
</template>
