<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import IftaLabel from 'primevue/iftalabel';
import InputOtp from 'primevue/inputotp';
import InputText from 'primevue/inputtext';
import AuthSubmit from '../../components/AuthSubmit.vue';
import AuthCard from '../../layouts/AuthCard.vue';

defineProps({
    canUseEmailOtp: { type: Boolean, default: false },
});

const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });

const submit = () =>
    form.post(route('two-factor.challenge'), { onFinish: () => form.reset('code', 'recovery_code') });
</script>

<template>
    <Head title="Verificación en dos pasos" />

    <AuthCard
        title="Verificación en dos pasos"
        :subtitle="
            useRecovery
                ? 'Escribe uno de tus códigos de recuperación.'
                : 'Escribe el código de seis dígitos de tu aplicación de autenticación.'
        "
        heading="Seguridad reforzada"
        tagline="Un segundo factor mantiene tu cuenta a salvo aunque alguien tenga tu contraseña."
    >
        <template #default>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div v-if="!useRecovery" class="flex flex-col items-center gap-2">
                    <label for="code" class="self-start text-sm font-medium">Código</label>
                    <InputOtp v-model="form.code" input-id="code" :length="6" integer-only autofocus />
                    <small v-if="form.errors.code" class="self-start text-red-500">{{ form.errors.code }}</small>
                </div>

                <div v-else class="flex flex-col gap-1">
                    <IftaLabel>
                        <InputText
                            id="recovery_code"
                            v-model="form.recovery_code"
                            autocomplete="one-time-code"
                            required
                            autofocus
                            fluid
                            :invalid="!!form.errors.recovery_code"
                        />
                        <label for="recovery_code">Código de recuperación</label>
                    </IftaLabel>
                    <small v-if="form.errors.recovery_code" class="text-red-500">
                        {{ form.errors.recovery_code }}
                    </small>
                </div>

                <AuthSubmit label="Verificar" icon="pi pi-check" :loading="form.processing" />
            </form>
        </template>

        <template #footer>
            <div class="flex flex-col items-center gap-1">
                <Button
                    :label="useRecovery ? 'Usar la aplicación de autenticación' : 'Usar un código de recuperación'"
                    severity="secondary"
                    variant="text"
                    size="small"
                    @click="useRecovery = !useRecovery"
                />
                <Link
                    v-if="canUseEmailOtp"
                    :href="route('otp.challenge')"
                    class="font-medium text-primary hover:underline"
                >
                    Enviarme un código por correo
                </Link>
            </div>
        </template>
    </AuthCard>
</template>
