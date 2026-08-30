<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import IconField from 'primevue/iconfield';
import IftaLabel from 'primevue/iftalabel';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import AuthSubmit from '../../components/AuthSubmit.vue';
import AuthCard from '../../layouts/AuthCard.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

const submit = () =>
    form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Crear cuenta" />

    <AuthCard
        title="Crear cuenta"
        subtitle="Regístrate para empezar a usar la aplicación."
        heading="Empieza en un minuto"
        tagline="Crea tu cuenta y ponte a construir desde el primer día."
    >
        <template #default>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-1">
                    <IftaLabel>
                        <IconField>
                            <InputText
                                id="name"
                                v-model="form.name"
                                autocomplete="name"
                                required
                                autofocus
                                fluid
                                :invalid="!!form.errors.name"
                            />
                            <InputIcon class="pi pi-user" />
                        </IconField>
                        <label for="name">Nombre</label>
                    </IftaLabel>
                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                </div>

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
                            autocomplete="new-password"
                            toggle-mask
                            required
                            fluid
                            :invalid="!!form.errors.password"
                        />
                        <label for="password">Contraseña</label>
                    </IftaLabel>
                    <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                </div>

                <IftaLabel>
                    <Password
                        v-model="form.password_confirmation"
                        input-id="password_confirmation"
                        autocomplete="new-password"
                        :feedback="false"
                        toggle-mask
                        required
                        fluid
                    />
                    <label for="password_confirmation">Repite la contraseña</label>
                </IftaLabel>

                <AuthSubmit label="Crear cuenta" :loading="form.processing" />
            </form>
        </template>

        <template #footer>
            <span class="text-muted-color">¿Ya tienes cuenta?</span>
            <Link :href="route('login')" class="font-medium text-primary hover:underline">Inicia sesión</Link>
        </template>
    </AuthCard>
</template>
