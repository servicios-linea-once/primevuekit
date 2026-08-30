<script setup>
import { Head } from '@inertiajs/vue3';
import Card from 'primevue/card';
import AppLayout from '../../Layouts/AppLayout.vue';
import DeleteAccountForm from './Partials/DeleteAccountForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileForm from './Partials/UpdateProfileForm.vue';

defineProps({
    mustVerifyEmail: { type: Boolean, default: false },
    emailVerified: { type: Boolean, default: true },
});
</script>

<template>
    <Head title="Perfil" />

    <AppLayout title="Perfil">
        <Card>
            <template #title>Datos de la cuenta</template>
            <template #content>
                <UpdateProfileForm :must-verify-email="mustVerifyEmail" :email-verified="emailVerified" />
            </template>
        </Card>

        <Card>
            <template #title>Contraseña</template>
            <template #subtitle>Usa una contraseña larga y que no reutilices en otros sitios</template>
            <template #content>
                <UpdatePasswordForm />
            </template>
        </Card>

        <Card>
            <template #title>Eliminar la cuenta</template>
            <template #subtitle>Esta acción es irreversible</template>
            <template #content>
                <DeleteAccountForm />
            </template>
        </Card>
    </AppLayout>
</template>
