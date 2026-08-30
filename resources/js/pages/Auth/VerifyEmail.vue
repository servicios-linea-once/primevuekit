<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Message from 'primevue/message';
import AuthSubmit from '../../components/AuthSubmit.vue';
import AuthCard from '../../layouts/AuthCard.vue';

defineProps({
    status: { type: String, default: null },
});

const resend = useForm({});
const logout = useForm({});
</script>

<template>
    <Head title="Verifica tu correo" />

    <AuthCard
        title="Verifica tu correo"
        subtitle="Te hemos enviado un enlace de verificación. Ábrelo para continuar."
        heading="Un último paso"
        tagline="Confirma tu dirección de correo y tendrás la cuenta lista."
    >
        <template #default>
            <Message v-if="status === 'verification-link-sent'" severity="success" :closable="false">
                Te hemos enviado un enlace nuevo.
            </Message>

            <form @submit.prevent="resend.post(route('verification.send'))">
                <AuthSubmit label="Reenviar el enlace" icon="pi pi-envelope" :loading="resend.processing" />
            </form>
        </template>

        <template #footer>
            <Button
                label="Salir"
                icon="pi pi-sign-out"
                severity="secondary"
                variant="text"
                size="small"
                :loading="logout.processing"
                @click="logout.post(route('logout'))"
            />
        </template>
    </AuthCard>
</template>
