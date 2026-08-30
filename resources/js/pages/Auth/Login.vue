<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Checkbox from 'primevue/checkbox';
import IconField from 'primevue/iconfield';
import IftaLabel from 'primevue/iftalabel';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Password from 'primevue/password';
import AuthSubmit from '../../components/AuthSubmit.vue';
import AuthCard from '../../layouts/AuthCard.vue';

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: null },
});

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Iniciar sesión" />

    <AuthCard
        title="Iniciar sesión"
        subtitle="Accede a tu cuenta para continuar."
        heading="¡Bienvenido de nuevo!"
        tagline="Inicia sesión y continúa construyendo cosas increíbles."
    >
        <template #default>
            <Message v-if="status" severity="success" :closable="false">{{ status }}</Message>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-1">
                    <IftaLabel>
                        <IconField>
                            <InputText
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="username"
                                placeholder="tu@email.com"
                                required
                                autofocus
                                fluid
                                :invalid="!!form.errors.email"
                            />
                            <InputIcon class="pi pi-envelope" />
                        </IconField>
                        <label for="email">Correo electrónico</label>
                    </IftaLabel>
                    <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
                </div>

                <div class="flex flex-col gap-1">
                    <IftaLabel>
                        <Password
                            v-model="form.password"
                            input-id="password"
                            autocomplete="current-password"
                            :feedback="false"
                            toggle-mask
                            required
                            fluid
                            :invalid="!!form.errors.password"
                        />
                        <label for="password">Contraseña</label>
                    </IftaLabel>
                    <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.remember" input-id="remember" binary />
                        <label for="remember" class="text-sm">Recordarme</label>
                    </div>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        ¿Olvidaste tu contraseña?
                    </Link>
                </div>

                <AuthSubmit label="Iniciar sesión" :loading="form.processing" />
            </form>
        </template>

        <template #footer>
            <span class="text-muted-color">¿No tienes cuenta?</span>
            <Link :href="route('register')" class="font-medium text-primary hover:underline">Regístrate</Link>
        </template>
    </AuthCard>
</template>
