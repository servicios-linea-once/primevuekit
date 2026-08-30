<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import InputOtp from 'primevue/inputotp';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import AuthSubmit from '../../components/AuthSubmit.vue';
import AuthCard from '../../layouts/AuthCard.vue';

defineProps({
    totpEnabled: { type: Boolean, default: false },
    totpPending: { type: Boolean, default: false },
    emailOtpEnabled: { type: Boolean, default: false },
    qrCode: { type: String, default: null },
    recoveryCodes: { type: Array, default: () => [] },
    status: { type: String, default: null },
});

const enable = useForm({});
const disable = useForm({});
const regenerate = useForm({});
const confirm = useForm({ code: '' });
const otpOn = useForm({});
const otpOff = useForm({});
</script>

<template>
    <Head title="Verificación en dos pasos" />

    <AuthCard
        title="Verificación en dos pasos"
        subtitle="Aplicación de autenticación y código por correo."
        heading="Protege tu cuenta"
        tagline="Activa los dos factores y añade una capa más sobre tu contraseña."
    >
        <section class="flex flex-col gap-3">
            <Message v-if="status" severity="success" :closable="false">{{ status }}</Message>

            <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold">Aplicación de autenticación</h3>
                <Tag
                    :value="totpEnabled ? 'Activa' : totpPending ? 'Pendiente' : 'Inactiva'"
                    :severity="totpEnabled ? 'success' : totpPending ? 'warn' : 'secondary'"
                />
            </div>

            <template v-if="totpPending">
                <p class="text-sm text-muted-color">
                    Escanea este código con tu aplicación y confírmalo para activarlo.
                </p>

                <!-- SVG generado en el servidor con BaconQrCode: no hay llamadas externas. -->
                <div class="max-w-52 self-center rounded-xl border border-surface bg-white p-3" v-html="qrCode" />

                <div v-if="recoveryCodes.length" class="flex flex-col gap-1 rounded-xl bg-surface-100 p-3 dark:bg-surface-800">
                    <p class="text-sm font-medium">Guarda estos códigos de recuperación:</p>
                    <ul class="grid grid-cols-2 gap-1 font-mono text-sm">
                        <li v-for="code in recoveryCodes" :key="code">{{ code }}</li>
                    </ul>
                </div>

                <form
                    class="flex flex-col items-start gap-2"
                    @submit.prevent="confirm.post(route('two-factor.confirm'))"
                >
                    <label for="code" class="text-sm font-medium">Código de la aplicación</label>
                    <InputOtp v-model="confirm.code" input-id="code" :length="6" integer-only />
                    <small v-if="confirm.errors.code" class="text-red-500">{{ confirm.errors.code }}</small>
                    <AuthSubmit label="Confirmar" icon="pi pi-check" :loading="confirm.processing" />
                </form>
            </template>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="!totpEnabled && !totpPending"
                    label="Activar"
                    icon="pi pi-shield"
                    :loading="enable.processing"
                    @click="enable.post(route('two-factor.enable'))"
                />
                <Button
                    v-if="totpEnabled"
                    label="Regenerar códigos"
                    icon="pi pi-refresh"
                    severity="secondary"
                    :loading="regenerate.processing"
                    @click="regenerate.post(route('two-factor.recovery-codes'))"
                />
                <Button
                    v-if="totpEnabled || totpPending"
                    label="Desactivar"
                    icon="pi pi-times"
                    severity="danger"
                    variant="outlined"
                    :loading="disable.processing"
                    @click="disable.delete(route('two-factor.disable'))"
                />
            </div>
        </section>

        <Divider />

        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold">Código por correo</h3>
                <Tag
                    :value="emailOtpEnabled ? 'Activo' : 'Inactivo'"
                    :severity="emailOtpEnabled ? 'success' : 'secondary'"
                />
            </div>

            <p class="text-sm text-muted-color">
                {{
                    emailOtpEnabled
                        ? 'Al entrar te pediremos un código enviado a tu correo.'
                        : 'Actívalo para recibir un código por correo cada vez que entres.'
                }}
            </p>

            <Button
                v-if="!emailOtpEnabled"
                label="Activar"
                icon="pi pi-envelope"
                class="self-start"
                :loading="otpOn.processing"
                @click="otpOn.post(route('email-otp.enable'))"
            />
            <Button
                v-else
                label="Desactivar"
                icon="pi pi-times"
                severity="danger"
                variant="outlined"
                class="self-start"
                :loading="otpOff.processing"
                @click="otpOff.delete(route('email-otp.disable'))"
            />
        </section>
    </AuthCard>
</template>
