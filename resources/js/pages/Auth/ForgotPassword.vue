<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import IconField from 'primevue/iconfield';
import IftaLabel from 'primevue/iftalabel';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import AuthSubmit from '../../components/AuthSubmit.vue';
import AuthCard from '../../layouts/AuthCard.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({ email: '' });

const submit = () => form.post(route('password.email'));
</script>

<template>
    <Head title="Recuperar contraseña" />

    <AuthCard
        title="Recuperar contraseña"
        subtitle="Te enviaremos un enlace para elegir una contraseña nueva."
        heading="¿Sin acceso?"
        tagline="Escribe tu correo y te mandamos un enlace para recuperar la cuenta."
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

                <AuthSubmit label="Enviar el enlace" icon="pi pi-send" :loading="form.processing" />
            </form>
        </template>

        <template #footer>
            <Link :href="route('login')" class="font-medium text-primary hover:underline">
                Volver a iniciar sesión
            </Link>
        </template>
    </AuthCard>
</template>
