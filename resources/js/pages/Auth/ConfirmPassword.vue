<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import IftaLabel from 'primevue/iftalabel';
import Password from 'primevue/password';
import AuthSubmit from '../../components/AuthSubmit.vue';
import AuthCard from '../../layouts/AuthCard.vue';

const form = useForm({ password: '' });

const submit = () => form.post(route('password.confirm'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Confirma tu contraseña" />

    <AuthCard
        title="Confirma tu contraseña"
        subtitle="Vuelve a escribir tu contraseña para continuar."
        heading="Zona protegida"
        tagline="Pedimos la contraseña otra vez antes de tocar la seguridad de tu cuenta."
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-1">
                <IftaLabel>
                    <Password
                        v-model="form.password"
                        input-id="password"
                        autocomplete="current-password"
                        :feedback="false"
                        toggle-mask
                        required
                        autofocus
                        fluid
                        :invalid="!!form.errors.password"
                    />
                    <label for="password">Contraseña</label>
                </IftaLabel>
                <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
            </div>

            <AuthSubmit label="Confirmar" icon="pi pi-lock-open" :loading="form.processing" />
        </form>
    </AuthCard>
</template>
