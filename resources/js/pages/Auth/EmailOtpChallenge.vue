<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from 'primevue/button';
import InputOtp from 'primevue/inputotp';
import Message from 'primevue/message';
import AuthSubmit from '../../components/AuthSubmit.vue';
import AuthCard from '../../layouts/AuthCard.vue';

const props = defineProps({
    email: { type: String, required: true },
    secondsUntilResend: { type: Number, default: 0 },
    ttlMinutes: { type: Number, default: 5 },
});

const page = usePage();
const status = computed(() => page.props.flash?.status ?? null);

const form = useForm({ code: '' });
const resend = useForm({});

const submit = () => form.post(route('otp.challenge'), { onFinish: () => form.reset('code') });
</script>

<template>
    <Head title="Código de verificación" />

    <AuthCard
        title="Código de verificación"
        :subtitle="`Hemos enviado un código a ${props.email}. Caduca en ${props.ttlMinutes} minutos.`"
        heading="Revisa tu correo"
        tagline="Te enviamos un código de un solo uso para completar el acceso."
    >
        <template #default>
            <Message v-if="status" severity="success" :closable="false">{{ status }}</Message>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col items-center gap-2">
                    <label for="code" class="self-start text-sm font-medium">Código</label>
                    <InputOtp v-model="form.code" input-id="code" :length="6" integer-only autofocus />
                    <small v-if="form.errors.code" class="self-start text-red-500">{{ form.errors.code }}</small>
                </div>

                <AuthSubmit label="Verificar" icon="pi pi-check" :loading="form.processing" />
            </form>
        </template>

        <template #footer>
            <Button
                :label="
                    props.secondsUntilResend > 0
                        ? `Reenviar en ${props.secondsUntilResend}s`
                        : 'Reenviar el código'
                "
                icon="pi pi-refresh"
                severity="secondary"
                variant="text"
                size="small"
                :disabled="props.secondsUntilResend > 0 || resend.processing"
                @click="resend.post(route('otp.resend'))"
            />
        </template>
    </AuthCard>
</template>
